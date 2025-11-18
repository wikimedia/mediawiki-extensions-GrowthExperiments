<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorWriter;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Extension\CommunityConfiguration\Provider\ConfigurationProviderFactory;
use MediaWiki\Extension\CommunityConfiguration\Store\WikiPageStore;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserOptionsLookup;
use MessageLocalizer;
use Wikimedia\Timestamp\ConvertibleTimestamp;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class MigrateMentorStatusAway extends LoggedUpdateMaintenance {

	private UserOptionsLookup $userOptionsLookup;
	private ConfigurationProviderFactory $providerFactory;
	private MessageLocalizer $messageLocalizer;
	// Copy of MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF
	private const MENTOR_AWAY_TIMESTAMP_PREF = 'growthexperiments-mentor-away-timestamp';
	private UserFactory $userFactory;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Copy growthexperiments-mentor-away-timestamp user option to GrowthMentorList config.' );
		$this->addOption( 'dry-run', 'print the config that would be saved and exit' );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	protected function doDBUpdates(): bool {
		$this->initServices();

		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		if ( !$user ) {
			$this->fatalError( 'Failed to create user' );
		}

		$provider = $this->providerFactory->newProvider( 'GrowthMentorList' );
		$store = $provider->getStore();
		if ( $store instanceof WikiPageStore && !$store->getConfigurationTitle()->exists() ) {
			$this->output( "No configuration page found, skipping..." );
			return true;
		}
		// $this->titleFactory->newFromTextThrow( $this->configLocation );
		$loadStatus = $provider->loadValidConfigurationUncached();
		if ( !$loadStatus->isOK() ) {
			$this->fatalError(
				Status::wrap( $loadStatus )->getWikiText( false, false, 'en' )
			);
		}

		$originalConfig = FormatJson::decode( FormatJson::encode( $loadStatus->getValue() ), true );
		// Make a copy of the config to be able to diff with the update blob
		$config = array_merge( [], $originalConfig );

		if ( !array_key_exists( CommunityStructuredMentorWriter::CONFIG_KEY, $config ) ) {
			$this->output( "Expected \"Mentors\" key to be present in config, exiting.\n" );
			return false;
		}

		if ( !$config[ CommunityStructuredMentorWriter::CONFIG_KEY ] ) {
			$this->output( "No mentors found in config, skipping migration.\n" );
			return true;
		}

		foreach ( $config[ CommunityStructuredMentorWriter::CONFIG_KEY ] as $mentorId => $mentorData ) {
			$mentor = $this->userFactory->newFromId( $mentorId );
			$awayTimestamp = $this->userOptionsLookup->getOption( $mentor, self::MENTOR_AWAY_TIMESTAMP_PREF );
			if ( $awayTimestamp ) {
				$config[CommunityStructuredMentorWriter::CONFIG_KEY][$mentor->getId()] = array_merge(
					$config[CommunityStructuredMentorWriter::CONFIG_KEY][$mentor->getId()],
					[
						'awayTimestamp' => ConvertibleTimestamp::convert( TS_ISO_8601, $awayTimestamp ),
					]
				);
			} elseif ( array_key_exists(
				'awayTimestamp',
				$config[CommunityStructuredMentorWriter::CONFIG_KEY][$mentor->getId()]
			) ) {
				unset( $config[CommunityStructuredMentorWriter::CONFIG_KEY][$mentor->getId()]['awayTimestamp'] );
			}
		}
		$additions = $this->arrayDiffAssocRecursive( $config, $originalConfig );
		$deletions = $this->arrayDiffAssocRecursive( $originalConfig, $config );
		if ( !$additions && !$deletions ) {
			$this->output( "Nothing new to save, skipping\n" );
			return false;
		}
		if ( $this->hasOption( 'dry-run' ) ) {
			$this->output( "There are changes:\n" );
			$this->output( "Additions:\n" );
			$this->output( FormatJson::encode( $additions, true ) . "\n" );
			$this->output( "Deletions:\n" );
			$this->output( FormatJson::encode( $deletions, true ) . "\n" );
			$this->output( "Would save:\n" );
			$this->output( FormatJson::encode( $config, true ) . "\n" );
			$validationStatus = $provider->getValidator()->validateStrictly( $config );
			$this->output( "Validation status:\n" );
			$this->output( $validationStatus );
			$this->output( "\n" );
			return false;
		}

		$summaryAsWikitext = $this->messageLocalizer->msg(
			'communityconfiguration-maintenance-config-change-summary'
		)->params( $this->getOption( 'summary', '' ) )->inContentLanguage()->text();
		$saveStatus = $provider->alwaysStoreValidConfiguration(
			$config,
			$user,
			$summaryAsWikitext
		);
		if ( !$saveStatus->isOK() ) {
			$this->error( $saveStatus );
			return false;
		}

		$this->output( "Saved!\n" );
		return true;
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$ccServices = CommunityConfigurationServices::wrap( $services );
		$this->userOptionsLookup = $services->getUserOptionsLookup();
		$this->userFactory = $services->getUserFactory();
		$this->providerFactory = $ccServices->getConfigurationProviderFactory();
		$this->messageLocalizer = RequestContext::getMain();
	}

	private function arrayDiffAssocRecursive( array $array1, array $array2 ): array {
		$difference = [];
		foreach ( $array1 as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( !isset( $array2[$key] ) || !is_array( $array2[$key] ) ) {
					$difference[$key] = $value;
				} else {
					$new_diff = $this->arrayDiffAssocRecursive( $value, $array2[$key] );
					if ( $new_diff ) {
						$difference[$key] = $new_diff;
					}
				}
			} elseif ( !array_key_exists( $key, $array2 ) || $array2[$key] !== $value ) {
				$difference[$key] = $value;
			}
		}
		return $difference;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey(): string {
		return 'migrateMentorStatusAwayToCommunityConfiguration';
	}
}

$maintClass = MigrateMentorStatusAway::class;
require_once RUN_MAINTENANCE_IF_MAIN;
