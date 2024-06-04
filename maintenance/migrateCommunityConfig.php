<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\Config\CommunityConfigurationWikiPageConfigReader;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\GrowthExperimentsServices;
use IDBAccessObject;
use LoggedUpdateMaintenance;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Extension\CommunityConfiguration\Provider\ConfigurationProviderFactory;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use StatusValue;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script for migrating existing community config on-wiki config files from the
 * GrowthExperiments community configuration version 1.0 structure to the structure expected
 * by the Community configuration (2.0) provider definitions in extension.json#CommunityConfiguration/Providers.
 * These are:
 *  - extension.json#CommunityConfiguration/Providers/HelpPanel
 *  - extension.json#CommunityConfiguration/Providers/Mentorship
 *  - extension.json#CommunityConfiguration/Providers/GrowthHomepage
 *  - extension.json#CommunityConfiguration/Providers/GrowthSuggestedEdits
 */
class MigrateCommunityConfig extends LoggedUpdateMaintenance {

	private const MIGRATION_ALLOW_LIST = [
		'HelpPanel',
		'GrowthHomepage',
		'Mentorship',
		'GrowthSuggestedEdits'
	];

	// Map of existing rootProperty name to override name
	// (the name used in the existing config page)
	private const OVERRIDE_NAMES = [
		'image_recommendation' => 'image-recommendation',
		'section_image_recommendation' => 'section-image-recommendation',
		'link_recommendation' => 'link-recommendation',
	];

	private const SUGGESTED_EDITS_TARGET_PROPS = [
		'copyedit',
		'links',
		'references',
		'update',
		'expand',
		'section_image_recommendation',
		'image_recommendation',
		'link_recommendation',
	];

	private const SUGGESTED_EDITS_AUTCOMPUTED_PROPS = [
		'group',
		'type'
	];

	private const HELP_PANEL_TARGET_PROPS = [
		'GEHelpPanelHelpDeskPostOnTop',
		'GEHelpPanelAskMentor'
	];

	private const HOMEPAGE_TARGET_PROPS = [
		'GELevelingUpKeepGoingNotificationThresholds',
	];

	/** @var IConfigurationProvider[] */
	private array $providers;

	private TitleFactory $titleFactory;

	private WikiPageConfigLoader $wikiPageConfigLoader;

	private ConfigurationProviderFactory $configurationProviderFactory;

	private FormatterFactory $formatterFactory;

	private Config $growthConfig;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->requireExtension( 'CommunityConfiguration' );
		$this->addDescription(
			'Migrate existing community configuration from on-wiki config files to new file locations'
		);

		$this->addOption( 'dry-run', 'Print the migration summary.' );
	}

	private function initServices() {
		$services = MediaWikiServices::getInstance();

		$this->configurationProviderFactory = CommunityConfigurationServices::wrap( $services )
			->getConfigurationProviderFactory();
		$extensionProviders = $this->configurationProviderFactory->getSupportedKeys();

		foreach ( self::MIGRATION_ALLOW_LIST as $providerId ) {
			if ( in_array( $providerId, $extensionProviders ) ) {
				$this->providers[ $providerId ] = $this->configurationProviderFactory->newProvider( $providerId );
			}
		}

		$geServices = GrowthExperimentsServices::wrap( $services );
		$this->wikiPageConfigLoader = $geServices->getWikiPageConfigLoader();
		$this->growthConfig = $geServices->getGrowthConfig();
		$this->titleFactory = $services->getTitleFactory();
		$this->formatterFactory = $services->getFormatterFactory();
	}

	/**
	 * Iterate over the given provider schema root properties and try
	 * to get a value for the same property name in the existing Growth config.
	 *
	 * @param IConfigurationProvider $provider
	 * @param array $config
	 * @param bool $dryRun
	 * @return array<array<string>,array<string>> The first element contains an array with the names of the migrated
	 * config options. The second element contains an array with the names of the config options specified in
	 * the provider schema but not present in the wiki growth config.
	 */
	private function migrateToProvider( IConfigurationProvider $provider, array $config, bool $dryRun = false ): array {
		$missingConfigs = [];
		$migratedConfigs = [];
		$rootProperties = $provider->getValidator()->getSchemaBuilder()->getRootProperties();
		$providerId = $provider->getId();
		$props = [];

		foreach ( $rootProperties as $prop => $schema ) {
			$maybeOverriddenProp = self::OVERRIDE_NAMES[ $prop ] ?? $prop;
			// GrowthSuggestedEdits special casing
			if ( $providerId === 'GrowthSuggestedEdits' ) {
				if ( in_array( $prop, self::SUGGESTED_EDITS_TARGET_PROPS ) ) {
					// Unset autocomputed properties since they don't exist in the schema and the validator
					// will complain
					foreach ( self::SUGGESTED_EDITS_AUTCOMPUTED_PROPS as $autcomputedProp ) {
						unset( $config[$maybeOverriddenProp][$autcomputedProp] );
					}
				}
			}
			// GrowthSuggestedEdits special casing
			if ( $providerId === 'HelpPanel' ) {
				if (
					in_array( $maybeOverriddenProp, self::HELP_PANEL_TARGET_PROPS ) &&
					isset( $config[$maybeOverriddenProp] )
				) {
					if ( $maybeOverriddenProp === 'GEHelpPanelHelpDeskPostOnTop' ) {
						// Apply same transforms that CommunityConfigurationWikiPageConfigReader
						$config[$maybeOverriddenProp] = array_search(
							$config[$maybeOverriddenProp],
							CommunityConfigurationWikiPageConfigReader::MAP_POST_ON_TOP_VALUES
						);

					}
					if ( $maybeOverriddenProp === 'GEHelpPanelAskMentor' ) {
						// Apply same transforms that CommunityConfigurationWikiPageConfigReader
						$config[$maybeOverriddenProp] = array_search(
							$config[$maybeOverriddenProp],
							CommunityConfigurationWikiPageConfigReader::MAP_ASK_MENTOR_VALUES
						);
					}
				}
			}

			// GrowthSuggestedEdits special casing
			if ( $providerId === 'GrowthHomepage' ) {
				if (
					in_array( $maybeOverriddenProp, self::HOMEPAGE_TARGET_PROPS ) &&
					isset( $config[$maybeOverriddenProp] )
				) {
					if ( $maybeOverriddenProp === 'GELevelingUpKeepGoingNotificationThresholds' ) {
						$config[$maybeOverriddenProp] = $config[$maybeOverriddenProp][1];
					}
				}
			}

			if ( isset( $config[$maybeOverriddenProp] ) ) {
				$props[$prop] = $config[$maybeOverriddenProp];
				$migratedConfigs[] = $maybeOverriddenProp;
			} else {
				$missingConfigs[] = $prop;
			}
		}

		// Required to convert deeply nested arrays into objects
		$objectProps = json_decode( json_encode( (object)$props ) );
		if ( $dryRun ) {
			$validationStatus = $provider->getValidator()->validateStrictly( $objectProps );
			if ( !$validationStatus->isOK() ) {
				if ( $this->hasValidationError( $validationStatus ) ) {
					$this->output( "Errors found:\n" );
				}
				$this->error(
					$this->formatterFactory->getStatusFormatter( RequestContext::getMain() )->getWikiText(
						$validationStatus
					)
				);
			}
		} else {
			$PHAB = 'T359038';
			$storeStatus = $provider->storeValidConfiguration(
				$objectProps,
				new UltimateAuthority(
					User::newSystemUser( User::MAINTENANCE_SCRIPT_USER )
				),
				"machine-generated configuration for migrating GrowthExperiments community configurable" .
				" options to use CommunityConfiguration Extension ([[phab:$PHAB]])"
			);
			if ( !$storeStatus->isOK() ) {
				if ( $this->hasValidationError( $storeStatus ) ) {
					$this->output( "Errors found:\n" );
				}
				$this->fatalError(
					$this->formatterFactory->getStatusFormatter( RequestContext::getMain() )->getWikiText(
						$storeStatus
					)
				);
			}

		}
		return [
			$migratedConfigs,
			$missingConfigs
		];
	}

	/**
	 * @inheritDoc
	 * @throws MalformedTitleException
	 */
	protected function doDBUpdates() {
		$allMigratedConfigs = [];
		$this->initServices();
		$dryRun = $this->hasOption( 'dry-run' );

		$configBuckets = [
			'growth' => $this->getGrowthExperimentsCommunityConfig(
				$this->growthConfig->get( 'GEWikiConfigPageTitle' )
			),
			'tasks' => $this->getGrowthExperimentsCommunityConfig(
				$this->growthConfig->get( 'GENewcomerTasksConfigTitle' )
			),
		];

		foreach ( $configBuckets as $bucket ) {
			if ( $bucket instanceof StatusValue ) {
				if ( !$bucket->isOK() ) {
					$this->fatalError(
						$this->formatterFactory->getStatusFormatter( RequestContext::getMain() )->getWikiText( $bucket )
					);
				}
			}
		}

		$config = array_merge( $configBuckets['tasks'], $configBuckets['growth'] );
		foreach ( $this->providers as $providerId => $provider ) {
			$this->output( 'Migrating ' . $providerId . "\n\n" );
			[
				$migratedConfigs,
				$missingConfigs
			] = $this->migrateToProvider( $provider, $config, $dryRun );
			$this->output( count( $missingConfigs ) . " missing config options:\n" );
			$this->output( implode( "\n", array_values( $missingConfigs ) ) . "\n\n" );
			$this->output( count( $migratedConfigs ) . " migrated config options:\n" );
			$this->output( implode( "\n", array_values( $migratedConfigs ) ) . "\n\n" );
			$allMigratedConfigs = array_merge( $allMigratedConfigs, $migratedConfigs );
		}

		$untouchedConfigs = array_diff( array_keys( $config ), $allMigratedConfigs );
		$this->output( count( $untouchedConfigs ) . " untouched config options:\n" );
		$this->output( implode( "\n", array_values( $untouchedConfigs ) ) . "\n\n" );

		return !$dryRun;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'GrowthExperimentsMigrateCommunityConfig';
	}

	/**
	 * Load the GEWikiConfigPageTitle configured page
	 * @return array|StatusValue The content of the configuration page (as JSON
	 *   data in PHP-native format), or a StatusValue on error.
	 * @throws MalformedTitleException
	 */
	private function getGrowthExperimentsCommunityConfig( string $titleText ) {
		$title = $this->titleFactory->newFromTextThrow( $titleText );
		return $this->wikiPageConfigLoader->load( $title, IDBAccessObject::READ_LATEST );
	}

	/**
	 * @param StatusValue $storeStatus
	 * @return bool
	 */
	private function hasValidationError( StatusValue $storeStatus ): bool {
		return array_reduce( $storeStatus->getMessages(), static function ( $carry, $item ) {
			return $carry || $item->getKey() === 'communityconfiguration-schema-validation-error';
		}, false );
	}
}

$maintClass = MigrateCommunityConfig::class;
require_once RUN_MAINTENANCE_IF_MAIN;
