<?php

namespace GrowthExperiments\Homepage;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModules\Banner;
use GrowthExperiments\HomepageModules\CommunityUpdates;
use GrowthExperiments\HomepageModules\Help;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\HomepageModules\MentorshipOptIn;
use GrowthExperiments\HomepageModules\StartEditing;
use GrowthExperiments\HomepageModules\StartEmail;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\HomepageModules\WelcomeSurveyReminder;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use OutOfBoundsException;

/**
 * Container class for handling dependency injection of homepage modules.
 */
class HomepageModuleRegistry {

	private MediaWikiServices $services;

	/** @var callable[]|null id => factory method */
	private ?array $wiring = null;

	/** @var IDashboardModule[] id => module */
	private array $modules = [];

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

			'welcomesurveyreminder' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new WelcomeSurveyReminder(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getExperimentUserManager(),
					$services->getSpecialPageFactory(),
					$growthServices->getWelcomeSurveyFactory()
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
					$growthServices->getExperimentUserManager(),
					$pageViewInfoEnabled ? $services->get( 'PageViewService' ) : null,
					$growthServices->getNewcomerTasksConfigurationLoader(),
					$growthServices->getNewcomerTasksUserOptionsLookup(),
					$growthServices->getTaskSuggesterFactory()->create(),
					$services->getTitleFactory(),
					$growthServices->getProtectionFilter(),
					$services->getUserOptionsManager(),
					$growthServices->getLinkRecommendationFilter(),
					$growthServices->getImageRecommendationFilter(),
					$services->getStatsFactory(),
					$growthServices->getTopicRegistry(),
					$growthServices->getTaskTypeManager()
				);
			},

			'impact' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				$userOptionsLookup = $services->getUserOptionsLookup();
				return new Impact(
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getExperimentUserManager(),
					$context->getUser(),
					$growthServices->getUserImpactStore(),
					$growthServices->getUserImpactFormatter(),
					$growthServices->getUserDatabaseHelper(),
					SuggestedEdits::isEnabledForAnyone( $context->getConfig() ),
					SuggestedEdits::isActivated( $context->getUser(), $userOptionsLookup )
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
					$growthServices->getMentorManager(),
					$growthServices->getMentorStatusManager(),
					$services->getGenderCache(),
					$services->getUserEditTracker()
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
			},
			'community-updates' => static function (
				MediaWikiServices $services,
				IContextSource $context
			) {
				$growthServices = GrowthExperimentsServices::wrap( $services );
				return new CommunityUpdates(
					LoggerFactory::getInstance( 'GrowthExperiments' ),
					$context,
					$growthServices->getGrowthWikiConfig(),
					$growthServices->getExperimentUserManager(),
					CommunityConfigurationServices::wrap( $services )->getConfigurationProviderFactory(),
					$services->getUserEditTracker(),
					$services->getLinkRenderer(),
					$services->getTitleFactory(),
					$services->getMainWANObjectCache(),
					$services->getHttpRequestFactory()
				);
			},
		];
	}

}
