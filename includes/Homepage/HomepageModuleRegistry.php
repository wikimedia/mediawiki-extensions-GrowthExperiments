<?php

namespace GrowthExperiments\Homepage;

use ExtensionRegistry;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModules\Banner;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\MentorshipOptIn;
use GrowthExperiments\HomepageModules\StartEditing;
use GrowthExperiments\HomepageModules\StartEmail;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use IContextSource;
use MediaWiki\MediaWikiServices;
use OutOfBoundsException;

/**
 * Container class for handling dependency injection of homepage modules.
 */
class HomepageModuleRegistry {

	/** @var MediaWikiServices */
	private $services;

	/** @var callable[] id => factory method */
	private $wiring;

	/** @var IDashboardModule[] id => module */
	private $modules = [];

	/**
	 * @param MediaWikiServices $services
	 */
	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	/**
	 * @param string $id
	 * @param IContextSource $contextSource
	 * @return IDashboardModule
	 */
	public function get( string $id, IContextSource $contextSource ): IDashboardModule {
		if ( $this->modules[$id] ?? null ) {
			return $this->modules[$id];
		}
		if ( $this->wiring === null ) {
			$this->wiring = self::getWiring();
		}
		if ( !array_key_exists( $id, $this->wiring ) ) {
			throw new OutOfBoundsException( 'Module not found: ' . $id );
		}
		$this->modules[$id] = $this->wiring[$id]( $this->services, $contextSource );
		return $this->modules[$id];
	}

	/**
	 * @internal for testing only
	 * @return string[]
	 */
	public static function getModuleIds(): array {
		return array_keys( self::getWiring() );
	}

	/**
	 * Returns wiring callbacks for each module.
	 * The callback receives the service container and the request context,
	 * and must return a homepage module.
	 * @return callable[] module id => callback
	 */
	private static function getWiring() {
		return [
			'banner' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new Banner(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getExperimentUserManager()
				);
			},

			'startemail' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new StartEmail(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getExperimentUserManager()
				);
			},

			'suggested-edits' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				$pageViewInfoEnabled = ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' );
				return new SuggestedEdits(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getGrowthExperimentsCampaignConfig(),
					$growthServices->getEditInfoService(),
					$growthServices->getExperimentUserManager(),
					$pageViewInfoEnabled ? $services->get( 'PageViewService' ) : null,
					$growthServices->getNewcomerTasksConfigurationLoader(),
					$growthServices->getNewcomerTasksUserOptionsLookup(),
					$growthServices->getTaskSuggesterFactory()->create(),
					$services->getTitleFactory(),
					$growthServices->getProtectionFilter(),
					$services->getUserOptionsLookup(),
					$growthServices->getLinkRecommendationFilter(),
					$growthServices->getImageRecommendationFilter()
				);
			},

			'impact' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				$pageViewInfoEnabled = ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' );
				$userOptionsLookup = $services->getUserOptionsLookup();
				return new Impact(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getGrowthConfig()->get( 'GEHomepageImpactModuleEnabled' ),
					$services->getDBLoadBalancer()->getConnectionRef( DB_REPLICA ),
					$growthServices->getExperimentUserManager(),
					[
						'isSuggestedEditsEnabled' => SuggestedEdits::isEnabled( $context ),
						'isSuggestedEditsActivated' => SuggestedEdits::isActivated( $context, $userOptionsLookup ),
					],
					$services->getTitleFactory(),
					$pageViewInfoEnabled ? $services->get( 'PageViewService' ) : null
				);
			},

			'mentorship' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new Mentorship(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getExperimentUserManager(),
					$growthServices->getMentorManager()
				);
			},

			'mentorship-optin' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new MentorshipOptIn(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getExperimentUserManager(),
					$growthServices->getMentorManager()
				);
			},

			'help' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new Help(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getExperimentUserManager()
				);
			},

			'start-startediting' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new StartEditing(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getExperimentUserManager(),
					$services->getUserOptionsLookup()
				);
			}

		];
	}

}
