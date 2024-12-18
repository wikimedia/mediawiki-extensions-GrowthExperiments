<?php

use CirrusSearch\CirrusSearchServices;
use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\MediaWikiConfigReaderWrapper;
use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use GrowthExperiments\Config\WikiPageConfig;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\EventLogging\PersonalizedPraiseLogger;
use GrowthExperiments\ExperimentUserDefaultsManager;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPosterFactory;
use GrowthExperiments\HelpPanel\Tips\TipNodeRenderer;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\LevelingUp\LevelingUpManager;
use GrowthExperiments\MentorDashboard\MenteeOverview\DatabaseMenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataUpdater;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use GrowthExperiments\MentorDashboard\MenteeOverview\UncachedMenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MentorDashboardModuleRegistry;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseNotificationsDispatcher;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyConditionsLookup;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\Mentorship\MentorRemover;
use GrowthExperiments\Mentorship\Provider\AbstractStructuredMentorProvider;
use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorProvider;
use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorWriter;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\LegacyStructuredMentorProvider;
use GrowthExperiments\Mentorship\Provider\LegacyStructuredMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\ReassignMenteesFactory;
use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\NewcomerTasks\AddImage\ActionApiImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\CacheBackedImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddImage\EventBus\EventGateImageSuggestionFeedbackUpdater;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataService;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\AddImage\MvpImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ProductionImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ServiceImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\AddLinkSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddLink\DbBackedLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater;
use GrowthExperiments\NewcomerTasks\AddLink\LinkSubmissionRecorder;
use GrowthExperiments\NewcomerTasks\AddLink\PruningLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\ServiceLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\StaticLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddSectionImage\SectionImageRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\CachedSuggestionsInfo;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\CommunityConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ErrorForwardingConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\PageConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\LinkRecommendationFilter;
use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use GrowthExperiments\NewcomerTasks\NewcomerTasksInfo;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\SuggestionsInfo;
use GrowthExperiments\NewcomerTasks\TaskSetListener;
use GrowthExperiments\NewcomerTasks\TaskSuggester\CacheDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\QualityGateDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use GrowthExperiments\PeriodicMetrics\MetricsFactory;
use GrowthExperiments\UserDatabaseHelper;
use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use GrowthExperiments\UserImpact\DatabaseUserImpactStore;
use GrowthExperiments\UserImpact\SubpageUserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactFormatter;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\Util;
use GrowthExperiments\WelcomeSurveyFactory;
use MediaWiki\Api\ApiRawMessage;
use MediaWiki\Config\Config;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Extension\Thanks\ThanksServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\WikiMap\WikiMap;

return [

	'GrowthExperimentsAddImageSubmissionHandler' => static function (
		MediaWikiServices $services
	): AddImageSubmissionHandler {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new AddImageSubmissionHandler(
			static function () use ( $services ) {
				$cirrusSearchServices = CirrusSearchServices::wrap( $services );

				return $cirrusSearchServices->getWeightedTagsUpdater();
			},
			$geServices->getTaskSuggesterFactory(),
			$geServices->getNewcomerTasksUserOptionsLookup(),
			$services->getMainWANObjectCache(),
			$services->getUserIdentityUtils(),
			ExtensionRegistry::getInstance()->isLoaded( 'EventBus' ) ?
				$geServices->getEventGateImageSuggestionFeedbackUpdater() : null,
		);
	},

	'GrowthExperimentsAddLinkSubmissionHandler' => static function (
		MediaWikiServices $services
	): AddLinkSubmissionHandler {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new AddLinkSubmissionHandler(
			$growthServices->getLinkRecommendationHelper(),
			$growthServices->getLinkRecommendationStore(),
			$growthServices->getLinkSubmissionRecorder(),
			$services->getLinkBatchFactory(),
			$services->getTitleFactory(),
			$services->getUserIdentityUtils(),
			$growthServices->getTaskSuggesterFactory(),
			$growthServices->getNewcomerTasksUserOptionsLookup(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			LoggerFactory::getInstance( 'GrowthExperiments' )
		);
	},

	'GrowthExperimentsChangeMentorFactory' => static function (
		MediaWikiServices $services
	): ChangeMentorFactory {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new ChangeMentorFactory(
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			$geServices->getMentorManager(),
			$geServices->getMentorStore(),
			$services->getUserFactory(),
			$services->getDBLoadBalancerFactory()
		);
	},

	'GrowthExperimentsConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
	},

	'GrowthExperimentsCommunityConfig' => static function ( MediaWikiServices $services ): Config {
		if ( Util::useCommunityConfiguration() ) {
			return new MediaWikiConfigReaderWrapper(
				$services->get( 'CommunityConfiguration.MediaWikiConfigReader' ),
				$services->getMainConfig()
			);
		} else {
			return $services->get( 'GrowthExperimentsMultiConfig' );
		}
	},

	'GrowthExperimentsConfigValidatorFactory' => static function (
		MediaWikiServices $services
	): ConfigValidatorFactory {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new ConfigValidatorFactory(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			$geServices->getTaskTypeHandlerRegistry()
		);
	},

	'GrowthExperimentsMultiConfig' => static function ( MediaWikiServices $services ): Config {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new GrowthExperimentsMultiConfig(
			$geServices->getWikiPageConfig(),
			GlobalVarConfig::newInstance()
		);
	},

	'GrowthExperimentsWikiPageConfig' => static function ( MediaWikiServices $services ): Config {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new WikiPageConfig(
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			$services->getTitleFactory(),
			$geServices->getWikiPageConfigLoader(),
			$services->getMainConfig()->get( 'GEWikiConfigPageTitle' ),
			defined( 'MW_PHPUNIT_TEST' ) && $services->isStorageDisabled()
		);
	},

	'GrowthExperimentsExperimentUserManager' => static function (
		MediaWikiServices $services
	): ExperimentUserManager {
		return new ExperimentUserManager(
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			new ServiceOptions(
				ExperimentUserManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getUserOptionsManager(),
			$services->getUserOptionsLookup(),
			$services->getUserFactory()
		);
	},

	'GrowthExperimentsExperimentUserDefaultsManager' => static function (
		MediaWikiServices $services
	): ExperimentUserDefaultsManager {
		return new ExperimentUserDefaultsManager(
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			static function () use ( $services ) {
				return $services->getCentralIdLookup();
			},
			$services->getUserIdentityUtils()
		);
	},

	'GrowthExperimentsHomepageModuleRegistry' => static function (
		MediaWikiServices $services
	): HomepageModuleRegistry {
		return new HomepageModuleRegistry( $services );
	},

	'GrowthExperimentsImageRecommendationProvider' => static function (
		MediaWikiServices $services
	): ImageRecommendationProvider {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new CacheBackedImageRecommendationProvider(
			$services->getMainWANObjectCache(),
			$growthServices->getImageRecommendationProviderUncached(),
			$services->getStatsFactory()
		);
	},

	'GrowthExperimentsImageRecommendationProviderUncached' => static function (
		MediaWikiServices $services
	): ImageRecommendationProvider {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new ServiceImageRecommendationProvider(
			$services->getTitleFactory(),
			$services->getStatsFactory(),
			$growthServices->getImageRecommendationApiHandler(),
			$growthServices->getImageRecommendationMetadataProvider(),
			$growthServices->getAddImageSubmissionHandler(),
			$services->getMainConfig()->get( 'GEDeveloperSetup' )
		);
	},

	'GrowthExperimentsImageRecommendationApiHandler' => static function (
		MediaWikiServices $services
	): ImageRecommendationApiHandler {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getGrowthConfig();
		$apiHandlerType = $config->get( 'GEImageRecommendationApiHandler' );
		if ( $apiHandlerType === 'production' ) {
			return new ProductionImageRecommendationApiHandler(
				$services->getHttpRequestFactory(),
				$config->get( 'GEImageRecommendationServiceUrl' ),
				$config->get( 'GEImageRecommendationServiceWikiIdMasquerade' ) ??
					WikiMap::getCurrentWikiId(),
				$services->getGlobalIdGenerator(),
				null,
				$config->get( 'GEImageRecommendationServiceUseTitles' ),
				!$config->get( 'GEDeveloperSetup' )
			);
		} elseif ( $apiHandlerType === 'mvp' ) {
			return new MvpImageRecommendationApiHandler(
				$services->getHttpRequestFactory(),
				$config->get( 'GEImageRecommendationServiceUrl' ),
				'wikipedia',
				$services->getContentLanguageCode()->toString(),
				$config->get( 'GEImageRecommendationServiceHttpProxy' ),
				null,
				$config->get( 'GEImageRecommendationServiceUseTitles' ) );
		} elseif ( $apiHandlerType === 'actionapi' ) {
			return new ActionApiImageRecommendationApiHandler(
				$services->getHttpRequestFactory(),
				$config->get( 'GEImageRecommendationServiceUrl' ),
				$config->get( 'GEImageRecommendationServiceAccessToken' )
			);
		} else {
			throw new DomainException( 'Invalid GEImageRecommendationApiHandler value: ' );
		}
	},

	'GrowthExperimentsLinkRecommendationHelper' => static function (
		MediaWikiServices $services
	): LinkRecommendationHelper {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new LinkRecommendationHelper(
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getLinkRecommendationProvider(),
			$growthServices->getLinkRecommendationStore(),
			static function () use ( $services ) {
				$cirrusSearchServices = CirrusSearchServices::wrap( $services );

				return $cirrusSearchServices->getWeightedTagsUpdater();
			}
		);
	},

	'GrowthExperimentsLinkRecommendationProviderUncached' => static function (
		MediaWikiServices $services
	): LinkRecommendationProvider {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getGrowthConfig();
		$serviceUrl = $config->get( 'GELinkRecommendationServiceUrl' );
		// In developer setups, the recommendation service is usually suggestion link targets
		// from a different wiki, which might end up being red links locally. Allow these,
		// otherwise we'd get mostly failures when trying to generate new tasks.
		$pruneRedLinks = !$config->get( 'GEDeveloperSetup' );
		if ( $serviceUrl ) {
			$rawProvider = new ServiceLinkRecommendationProvider(
				$services->getTitleFactory(),
				$services->getRevisionLookup(),
				$services->getHttpRequestFactory(),
				$config->get( 'GELinkRecommendationServiceUrl' ),
				$config->get( 'GELinkRecommendationServiceWikiIdMasquerade' ) ??
					WikiMap::getCurrentWikiId(),
				$services->getContentLanguageCode()->toString(),
				$config->get( 'GELinkRecommendationServiceAccessToken' ),
				$config->get( 'GELinkRecommendationServiceTimeout' )
			);
			return new PruningLinkRecommendationProvider(
				$services->getTitleFactory(),
				$services->getLinkBatchFactory(),
				$growthServices->getLinkRecommendationStore(),
				$rawProvider,
				$pruneRedLinks
			);

		} else {
			return new StaticLinkRecommendationProvider( [],
				StatusValue::newFatal( 'rawmessage', '$wgGELinkRecommendationServiceUrl not set!' ) );
		}
	},

	'GrowthExperimentsLinkRecommendationProvider' => static function (
		MediaWikiServices $services
	): LinkRecommendationProvider {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$useFallback = $growthServices->getGrowthConfig()->get( 'GELinkRecommendationFallbackOnDBMiss' );
		$uncachedProvider = $services->get( 'GrowthExperimentsLinkRecommendationProviderUncached' );
		// In developer setups, the recommendation service is usually suggestion link targets
		// from a different wiki, which might end up being red links locally. Allow these,
		// otherwise we'd get mostly failures when trying to generate new tasks.
		$pruneRedLinks = !$growthServices->getGrowthConfig()->get( 'GEDeveloperSetup' );
		if ( !$uncachedProvider instanceof StaticLinkRecommendationProvider ) {
			$rawProvider = new DbBackedLinkRecommendationProvider(
				GrowthExperimentsServices::wrap( $services )->getLinkRecommendationStore(),
				$useFallback ? $uncachedProvider : null,
				$services->getTitleFormatter()
			);
			return new PruningLinkRecommendationProvider(
				$services->getTitleFactory(),
				$services->getLinkBatchFactory(),
				$growthServices->getLinkRecommendationStore(),
				$rawProvider,
				$pruneRedLinks
			);
		} else {
			return $uncachedProvider;
		}
	},

	'GrowthExperimentsLinkRecommendationStore' => static function (
		MediaWikiServices $services
	): LinkRecommendationStore {
		$loadBalancer = GrowthExperimentsServices::wrap( $services )->getLoadBalancer();
		return new LinkRecommendationStore(
			$loadBalancer,
			$services->getTitleFactory(),
			$services->getLinkBatchFactory(),
			$services->getPageStore()
		);
	},

	'GrowthExperimentsLinkRecommendationUpdater' => static function (
		MediaWikiServices $services
	): LinkRecommendationUpdater {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new LinkRecommendationUpdater(
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			$services->getDBLoadBalancerFactory(),
			$services->getRevisionStore(),
			$services->getNameTableStoreFactory()->getChangeTagDef(),
			$services->getPageProps(),
			$services->getChangeTagsStore(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			static function () use ( $services ) {
				$cirrusSearchServices = CirrusSearchServices::wrap( $services );

				return $cirrusSearchServices->getWeightedTagsUpdater();
			},
			$services->get( 'GrowthExperimentsLinkRecommendationProviderUncached' ),
			$growthServices->getLinkRecommendationStore()
		);
	},

	'GrowthExperimentsLinkSubmissionRecorder' => static function (
		MediaWikiServices $services
	): LinkSubmissionRecorder {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new LinkSubmissionRecorder(
			$services->getTitleParser(),
			$services->getLinkBatchFactory(),
			$growthServices->getLinkRecommendationStore()
		);
	},

	'GrowthExperimentsMenteeOverviewDataProvider' => static function (
		MediaWikiServices $services
	): MenteeOverviewDataProvider {
		return $services->get( 'GrowthExperimentsMenteeOverviewDataProviderDatabase' );
	},

	'GrowthExperimentsMenteeOverviewDataProviderDatabase' => static function (
		MediaWikiServices $services
	): DatabaseMenteeOverviewDataProvider {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new DatabaseMenteeOverviewDataProvider(
			$services->getMainWANObjectCache(),
			$geServices->getMentorStore(),
			$geServices->getLoadBalancer()
		);
	},

	'GrowthExperimentsMenteeOverviewDataProviderUncached' => static function (
		MediaWikiServices $services
	): UncachedMenteeOverviewDataProvider {
		$geServices = GrowthExperimentsServices::wrap( $services );
		$provider = new UncachedMenteeOverviewDataProvider(
			$geServices->getMentorStore(),
			$services->getChangeTagDefStore(),
			$services->getActorMigration(),
			$services->getUserIdentityLookup(),
			$services->getTempUserConfig(),
			$services->getDBLoadBalancerFactory()
		);
		$provider->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $provider;
	},

	'GrowthExperimentsMenteeOverviewDataUpdater' => static function (
		MediaWikiServices $services
	): MenteeOverviewDataUpdater {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new MenteeOverviewDataUpdater(
			$geServices->getUncachedMenteeOverviewDataProvider(),
			$geServices->getMenteeOverviewDataProvider(),
			$geServices->getMentorStore(),
			$services->getUserOptionsManager(),
			$services->getDBLoadBalancerFactory(),
			$geServices->getLoadBalancer()
		);
	},

	'GrowthExperimentsMentorDashboardModuleRegistry' => static function (
		MediaWikiServices $services
	): MentorDashboardModuleRegistry {
		return new MentorDashboardModuleRegistry( $services );
	},

	'GrowthExperimentsMentorManager' => static function (
		MediaWikiServices $services
	): MentorManager {
		$geServices = GrowthExperimentsServices::wrap( $services );

		$manager = new MentorPageMentorManager(
			$geServices->getMentorStore(),
			$geServices->getMentorStatusManager(),
			$geServices->getMentorProvider(),
			$services->getUserIdentityLookup(),
			$services->getUserOptionsLookup(),
			$services->getUserOptionsManager(),
			RequestContext::getMain()->getRequest()->wasPosted()
		);
		$manager->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $manager;
	},

	'GrowthExperimentsMentorProvider' => static function (
		MediaWikiServices $services
	): MentorProvider {
		return GrowthExperimentsServices::wrap( $services )
			->getMentorProviderStructured();
	},

	'GrowthExperimentsMentorProviderStructured' => static function (
		MediaWikiServices $services
	): AbstractStructuredMentorProvider {
		$geServices = GrowthExperimentsServices::wrap( $services );

		if ( Util::useCommunityConfiguration() ) {
			$provider = new CommunityStructuredMentorProvider(
				$services->getUserIdentityLookup(),
				new DerivativeContext( RequestContext::getMain() ),
				CommunityConfigurationServices::wrap( $services )
					->getConfigurationProviderFactory()->newProvider( 'GrowthMentorList' )
			);
		} else {
			$provider = new LegacyStructuredMentorProvider(
				$services->getUserIdentityLookup(),
				new DerivativeContext( RequestContext::getMain() ),
				$geServices->getWikiPageConfigLoader(),
				$services->getTitleFactory()->newFromText(
					$services->getMainConfig()->get( 'GEStructuredMentorList' )
				)
			);
		}

		$provider->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $provider;
	},

	'GrowthExperimentsMentorRemover' => static function (
		MediaWikiServices $services
	): MentorRemover {
		$geServices = GrowthExperimentsServices::wrap( $services );

		return new MentorRemover(
			$geServices->getMentorProvider(),
			$geServices->getMentorWriter(),
			$geServices->getReassignMenteesFactory()
		);
	},

	'GrowthExperimentsMentorStatusManager' => static function (
		MediaWikiServices $services
	): MentorStatusManager {
		return new MentorStatusManager(
			$services->getUserOptionsManager(),
			$services->getUserIdentityLookup(),
			$services->getUserFactory(),
			$services->getDBLoadBalancerFactory()
		);
	},

	'GrowthExperimentsMentorStore' => static function ( MediaWikiServices $services ): MentorStore {
		return GrowthExperimentsServices::wrap( $services )->getDatabaseMentorStore();
	},

	'GrowthExperimentsMentorStoreDatabase' => static function ( MediaWikiServices $services ): DatabaseMentorStore {
		$geServices = GrowthExperimentsServices::wrap( $services );
		$lb = $geServices->getLoadBalancer();

		$store = new DatabaseMentorStore(
			$services->getMainWANObjectCache(),
			$services->getUserFactory(),
			$services->getUserIdentityLookup(),
			$services->getJobQueueGroup(),
			$lb,
			defined( 'MEDIAWIKI_JOB_RUNNER' ) ||
				MW_ENTRY_POINT === 'cli' ||
				RequestContext::getMain()->getRequest()->wasPosted()
		);
		$store->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $store;
	},

	'GrowthExperimentsMentorWriter' => static function (
		MediaWikiServices $services
	): IMentorWriter {
		$geServices = GrowthExperimentsServices::wrap( $services );

		if ( Util::useCommunityConfiguration() ) {
			$writer = new CommunityStructuredMentorWriter(
				$geServices->getMentorProvider(),
				$services->getUserIdentityLookup(),
				$services->getUserFactory(),
				$services->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
				CommunityConfigurationServices::wrap( $services )
					->getConfigurationProviderFactory()->newProvider( 'GrowthMentorList' )
			);
		} else {
			$writer = new LegacyStructuredMentorWriter(
				$geServices->getMentorProvider(),
				$services->getUserIdentityLookup(),
				$services->getUserFactory(),
				$geServices->getWikiPageConfigLoader(),
				$geServices->getWikiPageConfigWriterFactory(),
				$services->getTitleFactory()->newFromText(
					$services->getMainConfig()->get( 'GEStructuredMentorList' )
				)
			);
		}

		$writer->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $writer;
	},

	'GrowthExperimentsMetricsFactory' => static function (
		MediaWikiServices $services
	): MetricsFactory {
		return new MetricsFactory(
			$services->getDBLoadBalancer(),
			$services->getUserEditTracker(),
			$services->getUserIdentityLookup(),
			GrowthExperimentsServices::wrap( $services )->getMentorProvider(),
		);
	},

	'GrowthExperimentsNewcomerTasksConfigurationLoader' => static function (
		MediaWikiServices $services
	): ConfigurationLoader {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getGrowthConfig();

		$taskConfigTitle = $config->get( 'GENewcomerTasksConfigTitle' );
		if ( !$taskConfigTitle ) {
			return new ErrorForwardingConfigurationLoader( StatusValue::newFatal( new ApiRawMessage(
				'The ConfigurationLoader has not been configured!',
				'configurationloader-not-configured'
			) ) );
		}

		$topicType = $config->get( 'GENewcomerTasksTopicType' );
		$topicConfigTitle = null;
		$topicConfigData = null;
		if ( $topicType === PageConfigurationLoader::CONFIGURATION_TYPE_ORES ) {
			$topicConfigData = $config->get( 'GENewcomerTasksOresTopicConfig' );
		} elseif ( $topicType === PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE ) {
			$topicConfigTitle = $config->get( 'GENewcomerTasksTopicConfigTitle' );
		}

		if ( Util::useCommunityConfiguration() ) {
			if ( $topicType !== PageConfigurationLoader::CONFIGURATION_TYPE_ORES ) {
				throw new LogicException(
					'Topic type ' . $topicType . ' is not supported when ' .
					'the CommunityConfiguration extension is enabled.'
				);
			}

			$providerFactory = CommunityConfigurationServices::wrap( $services )
				->getConfigurationProviderFactory();
			$suggestedEditsProvider = in_array( 'GrowthSuggestedEdits', $providerFactory->getSupportedKeys() ) ?
				$providerFactory->newProvider( 'GrowthSuggestedEdits' ) : null;

			$configurationLoader = new CommunityConfigurationLoader(
				$growthServices->getNewcomerTasksConfigurationValidator(),
				$growthServices->getTaskTypeHandlerRegistry(),
				$topicType,
				$suggestedEditsProvider,
				$services->getTitleFactory(),
				$topicConfigData,
				LoggerFactory::getInstance( 'GrowthExperiments' ),
			);
		} else {
			$configurationLoader = new PageConfigurationLoader(
				$growthServices->getNewcomerTasksConfigurationValidator(),
				$growthServices->getTaskTypeHandlerRegistry(),
				$topicType,
				$services->getTitleFactory(),
				$growthServices->getWikiPageConfigLoader(),
				$taskConfigTitle,
				$topicConfigTitle,
				$growthServices->getGrowthWikiConfig(),
			);
		}

		if ( !$config->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) ) {
			$configurationLoader->disableTaskType( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}
		if ( !$config->get( 'GENewcomerTasksImageRecommendationsEnabled' ) ) {
			$configurationLoader->disableTaskType( ImageRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}
		if ( !$config->get( 'GENewcomerTasksSectionImageRecommendationsEnabled' ) ) {
			$configurationLoader->disableTaskType( SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}

		$configurationLoader->setCampaignConfigCallback( static function () use ( $growthServices ) {
			return $growthServices->getGrowthExperimentsCampaignConfig();
		} );

		return $configurationLoader;
	},

	'GrowthExperimentsNewcomerTasksConfigurationValidator' => static function (
		MediaWikiServices $services
	): ConfigurationValidator {
		return new ConfigurationValidator(
			RequestContext::getMain(),
			$services->getCollationFactory(),
			$services->getTitleParser()
		);
	},

	'GrowthExperimentsNewcomerTasksUserOptionsLookup' => static function (
		MediaWikiServices $services
	): NewcomerTasksUserOptionsLookup {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new NewcomerTasksUserOptionsLookup(
			$growthServices->getExperimentUserManager(),
			$services->getUserOptionsLookup(),
			$services->getMainConfig(),
			$growthServices->getNewcomerTasksConfigurationLoader()
		);
	},

	'GrowthExperimentsProtectionFilter' => static function (
		MediaWikiServices $services
	): ProtectionFilter {
		return new ProtectionFilter(
			$services->getTitleFactory(),
			$services->getLinkBatchFactory(),
			$services->getDBLoadBalancerFactory()
		);
	},

	'GrowthExperimentsLinkRecommendationFilter' => static function (
		MediaWikiServices $services
	): LinkRecommendationFilter {
		return new LinkRecommendationFilter(
			GrowthExperimentsServices::wrap( $services )->getLinkRecommendationStore()
		);
	},

	'GrowthExperimentsImageRecommendationFilter' => static function (
		MediaWikiServices $services
	): ImageRecommendationFilter {
		return new ImageRecommendationFilter(
			$services->getMainWANObjectCache()
		);
	},

	'GrowthExperimentsPersonalizedPraiseLogger' => static function (
		MediaWikiServices $services
	): PersonalizedPraiseLogger {
		$geServices = GrowthExperimentsServices::wrap( $services );

		return new PersonalizedPraiseLogger(
			$geServices->getPersonalizedPraiseSettings()
		);
	},

	'GrowthExperimentsPersonalizedPraiseNotificationsDispatcher' => static function (
		MediaWikiServices $services
	): PersonalizedPraiseNotificationsDispatcher {
		$geServices = GrowthExperimentsServices::wrap( $services );

		return new PersonalizedPraiseNotificationsDispatcher(
			$services->getMainConfig(),
			$services->getMainObjectStash(),
			$services->getSpecialPageFactory(),
			$geServices->getPersonalizedPraiseSettings(),
			$geServices->getPersonalizedPraiseLogger()
		);
	},

	'GrowthExperimentsPersonalizedPraiseSettings' => static function (
		MediaWikiServices $services
	): PersonalizedPraiseSettings {
		$geServices = GrowthExperimentsServices::wrap( $services );

		return new PersonalizedPraiseSettings(
			$geServices->getGrowthWikiConfig(),
			RequestContext::getMain(),
			$services->getUserOptionsManager(),
			$services->getUserFactory(),
			$services->getTitleFactory(),
			$services->getRevisionLookup()
		);
	},

	'GrowthExperimentsPraiseworthyConditionsLookup' => static function (
		MediaWikiServices $services
	): PraiseworthyConditionsLookup {
		$geServices = GrowthExperimentsServices::wrap( $services );

		return new PraiseworthyConditionsLookup(
			$geServices->getPersonalizedPraiseSettings(),
			$services->getUserOptionsLookup(),
			$services->getUserFactory(),
			$geServices->getMentorManager()
		);
	},

	'GrowthExperimentsPraiseworthyMenteeSuggester' => static function (
		MediaWikiServices $services
	): PraiseworthyMenteeSuggester {
		$geServices = GrowthExperimentsServices::wrap( $services );

		$suggester = new PraiseworthyMenteeSuggester(
			$services->getMainObjectStash(),
			$services->getUserOptionsManager(),
			$geServices->getPraiseworthyConditionsLookup(),
			$geServices->getPersonalizedPraiseNotificationsDispatcher(),
			$geServices->getPersonalizedPraiseLogger(),
			$geServices->getMentorStore(),
			$geServices->getUserImpactStore()
		);
		$suggester->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $suggester;
	},

	'GrowthExperimentsQuestionPosterFactory' => static function (
		MediaWikiServices $services
	): QuestionPosterFactory {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new QuestionPosterFactory(
			$services->getWikiPageFactory(),
			$services->getTitleFactory(),
			$growthServices->getMentorManager(),
			$services->getPermissionManager(),
			$growthServices->getGrowthWikiConfig()->get( 'GEHelpPanelHelpDeskPostOnTop' ),
			$services->getStatsFactory(),
			ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ),
			ExtensionRegistry::getInstance()->isLoaded( 'Flow' )
		);
	},

	'GrowthExperimentsReassignMenteesFactory' => static function (
		MediaWikiServices $services
	): ReassignMenteesFactory {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new ReassignMenteesFactory(
			$growthServices->getLoadBalancer(),
			$growthServices->getMentorManager(),
			$growthServices->getMentorProvider(),
			$growthServices->getMentorStore(),
			$growthServices->getChangeMentorFactory(),
			$services->getJobQueueGroupFactory(),
			$services->getFormatterFactory()
		);
	},

	'GrowthExperimentsStarredMenteesStore' => static function (
		MediaWikiServices $services
	): StarredMenteesStore {
		return new StarredMenteesStore(
			$services->getUserIdentityLookup(),
			$services->getUserOptionsManager()
		);
	},

	'GrowthExperimentsTaskSuggesterFactory' => static function (
		MediaWikiServices $services
	): TaskSuggesterFactory {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$growthConfig = $growthServices->getGrowthConfig();

		$taskTypeHandlerRegistry = $growthServices->getTaskTypeHandlerRegistry();
		$configLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );
		$isCirrusSearchLoadedAndConfigured = ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' )
			&& $services->getSearchEngineConfig()->getSearchType() === 'CirrusSearch';

		if ( $growthConfig->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			$taskSuggesterFactory = new RemoteSearchTaskSuggesterFactory(
				$taskTypeHandlerRegistry,
				$configLoader,
				$searchStrategy,
				$growthServices->getNewcomerTasksUserOptionsLookup(),
				$services->getHttpRequestFactory(),
				$services->getTitleFactory(),
				$services->getLinkBatchFactory(),
				$growthConfig->get( 'GENewcomerTasksRemoteApiUrl' )
			);
		} elseif ( $isCirrusSearchLoadedAndConfigured ) {
			$taskSuggesterFactory = new LocalSearchTaskSuggesterFactory(
				$taskTypeHandlerRegistry,
				$configLoader,
				$searchStrategy,
				$growthServices->getNewcomerTasksUserOptionsLookup(),
				$services->getSearchEngineFactory(),
				$services->getLinkBatchFactory(),
				$services->getStatsFactory(),
				$services->getStatsdDataFactory()
			);
			$taskSuggesterFactory = new DecoratingTaskSuggesterFactory(
				$taskSuggesterFactory,
				$services->getObjectFactory(),
				[
					[
						'class' => CacheDecorator::class,
						'args' => [
							$services->getJobQueueGroupFactory()->makeJobQueueGroup(),
							$services->getMainWANObjectCache(),
							new TaskSetListener(
								$services->getMainWANObjectCache(),
								$services->getStatsFactory()
							),
							$services->getJsonCodec()
						],
					],
					[
						'class' => QualityGateDecorator::class,
						'args' => [
							$growthServices->getNewcomerTasksConfigurationLoader(),
							$growthServices->getImageRecommendationSubmissionLogFactory(),
							$growthServices->getSectionImageRecommendationSubmissionLogFactory(),
							$growthServices->getLinkRecommendationSubmissionLogFactory(),
							$growthServices->getGrowthExperimentsCampaignConfig()
						]
					],
				]
			);
		} else {
			$taskSuggesterFactory = new StaticTaskSuggesterFactory(
				new ErrorForwardingTaskSuggester(
					StatusValue::newFatal( new ApiRawMessage(
						'TaskSuggesterFactory is not configured. ' .
						'Either $wgGENewcomerTasksRemoteApiUrl needs to be set or CirrusSearch needs to be enabled.',
						'tasksuggesterfactory-not-configured'
					) )
				)
			);
		}
		$taskSuggesterFactory->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $taskSuggesterFactory;
	},

	'GrowthExperimentsSuggestionsInfo' => static function (
		MediaWikiServices $services
	): NewcomerTasksInfo {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new CachedSuggestionsInfo(
			new SuggestionsInfo(
				$growthServices->getTaskSuggesterFactory(),
				$growthServices->getTaskTypeHandlerRegistry(),
				$growthServices->getNewcomerTasksConfigurationLoader()
			),
			$services->getMainWANObjectCache()
		);
	},

	'GrowthExperimentsTaskTypeHandlerRegistry' => static function (
		MediaWikiServices $services
	): TaskTypeHandlerRegistry {
		$extensionConfig = GrowthExperimentsServices::wrap( $services )->getGrowthConfig();
		return new TaskTypeHandlerRegistry(
			$services->getObjectFactory(),
			$extensionConfig->get( 'GENewcomerTasksTaskTypeHandlers' )
		);
	},

	'GrowthExperimentsTipsAssembler' => static function (
		MediaWikiServices $services
	): TipsAssembler {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new TipsAssembler(
			$growthExperimentsServices->getNewcomerTasksConfigurationLoader(),
			$growthExperimentsServices->getTipNodeRenderer()
		);
	},

	'GrowthExperimentsTipNodeRenderer' => static function (
		MediaWikiServices $services
	): TipNodeRenderer {
		return new TipNodeRenderer(
			$services->getMainConfig()->get( 'ExtensionAssetsPath' )
		);
	},

	'GrowthExperimentsWelcomeSurveyFactory' => static function (
		MediaWikiServices $services
	): WelcomeSurveyFactory {
		return new WelcomeSurveyFactory(
			$services->getLanguageNameUtils(),
			$services->getUserOptionsManager(),
			ExtensionRegistry::getInstance()->isLoaded( 'UniversalLanguageSelector' )
		);
	},

	'GrowthExperimentsWikiPageConfigLoader' => static function (
		MediaWikiServices $services
	): WikiPageConfigLoader {
		return new WikiPageConfigLoader(
			$services->getMainWANObjectCache(),
			GrowthExperimentsServices::wrap( $services )
				->getWikiPageConfigValidatorFactory(),
			$services->getHttpRequestFactory(),
			$services->getRevisionLookup(),
			$services->getTitleFactory(),
			$services->getUrlUtils(),
			defined( 'MW_PHPUNIT_TEST' ) && $services->isStorageDisabled()
		);
	},

	'GrowthExperimentsWikiPageConfigWriterFactory' => static function (
		MediaWikiServices $services
	): WikiPageConfigWriterFactory {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new WikiPageConfigWriterFactory(
			$growthExperimentsServices->getWikiPageConfigLoader(),
			$growthExperimentsServices->getWikiPageConfigValidatorFactory(),
			$services->getWikiPageFactory(),
			$services->getTitleFactory(),
			$services->getUserFactory(),
			$services->getHookContainer(),
			LoggerFactory::getInstance( 'GrowthExperiments' )
		);
	},

	'_GrowthExperimentsAQSConfig' => static function ( MediaWikiServices $services ): stdClass {
		// This is not a service and doesn't quite belong here, but we need to share it with
		// Javascript code as fetching this information in bulk is not feasible, and this seems
		// the least awkward option (as opposed to creating a dedicated service just for fetching
		// configuration, or passing through all the services involved here to the ResourceLoader
		// callback). The nice long-term solution is probably to extend RL callback specification
		// syntax to allow using something like the 'services' parameter of ObjectFactory.
		$project = $services->getMainConfig()->get( 'ServerName' );
		if ( ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' ) ) {
			$project = $services->getConfigFactory()->makeConfig( 'PageViewInfo' )
				->get( 'PageViewInfoWikimediaDomain' )
				?: $project;
		}
		// MediaWikiServices insists on service factories returning an object, so wrap it into one
		return (object)[ 'project' => $project ];
	},

	'GrowthExperimentsImageRecommendationMetadataService' => static function (
		MediaWikiServices $services
	): ImageRecommendationMetadataService {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new ImageRecommendationMetadataService(
			$services->getHttpRequestFactory(),
			$services->getRepoGroup(),
			$growthExperimentsServices->getGrowthConfig()->get( 'GEMediaInfoRepos' ),
			$services->getContentLanguageCode()->toString()
		);
	},

	'GrowthExperimentsImageRecommendationMetadataProvider' => static function (
		MediaWikiServices $services
	): ImageRecommendationMetadataProvider {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new ImageRecommendationMetadataProvider(
			$growthExperimentsServices->getImageRecommendationMetadataService(),
			$services->getContentLanguageCode()->toString(),
			$services->getContentLanguage()->getFallbackLanguages(),
			$services->getLanguageNameUtils(),
			new DerivativeContext( RequestContext::getMain() ),
			$services->getSiteStore()
		);
	},

	'GrowthExperimentsImageRecommendationSubmissionLogFactory' => static function (
		MediaWikiServices $services
	): ImageRecommendationSubmissionLogFactory {
		return new ImageRecommendationSubmissionLogFactory(
			$services->getDBLoadBalancerFactory(),
			$services->getUserOptionsLookup()
		);
	},

	'GrowthExperimentsSectionImageRecommendationSubmissionLogFactory' => static function (
		MediaWikiServices $services
	): SectionImageRecommendationSubmissionLogFactory {
		return new SectionImageRecommendationSubmissionLogFactory(
			$services->getDBLoadBalancerFactory(),
			$services->getUserOptionsLookup()
		);
	},

	'GrowthExperimentsLinkRecommendationSubmissionLogFactory' => static function (
		MediaWikiServices $services
	): LinkRecommendationSubmissionLogFactory {
		return new LinkRecommendationSubmissionLogFactory(
			$services->getDBLoadBalancerFactory(),
			$services->getUserOptionsLookup()
		);
	},

	'GrowthExperimentsCampaignConfig' => static function (
		MediaWikiServices $services
	): CampaignConfig {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new CampaignConfig(
			$growthServices->getGrowthWikiConfig()->get( 'GECampaigns' ) ?? [],
			$growthServices->getGrowthWikiConfig()->get( 'GECampaignTopics' ) ?? [],
			$services->getUserOptionsLookup()
		);
	},

	'GrowthExperimentsTemplateBasedTaskSubmissionHandler' => static function (
		MediaWikiServices $services
	): TemplateBasedTaskSubmissionHandler {
		return new TemplateBasedTaskSubmissionHandler();
	},

	'GrowthExperimentsNewcomerTasksChangeTagsManager' => static function (
		MediaWikiServices $services
	): NewcomerTasksChangeTagsManager {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new NewcomerTasksChangeTagsManager(
			$services->getUserOptionsLookup(),
			$growthServices->getTaskTypeHandlerRegistry(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$services->getRevisionLookup(),
			$services->getDBLoadBalancerFactory(),
			$services->getUserIdentityUtils(),
			$services->getChangeTagsStore(),
			$services->getStatsFactory()
		);
	},

	'GrowthExperimentsUserImpactLookup' => static function (
		MediaWikiServices $services
	): UserImpactLookup {
		return $services->get( 'GrowthExperimentsUserImpactLookup_Computed' );
	},

	'_GrowthExperimentsUserImpactLookup_Subpage' => static function (
		MediaWikiServices $services
	): UserImpactLookup {
		return new SubpageUserImpactLookup(
			$services->getWikiPageFactory()
		);
	},

	'GrowthExperimentsUserImpactLookup_Computed' => static function (
		MediaWikiServices $services
	): UserImpactLookup {
		$pageViewInfoLoaded = ExtensionRegistry::getInstance()->isLoaded( 'PageViewInfo' );
		$thanksQueryHelper = null;
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Thanks' ) ) {
			$thanksQueryHelper = ThanksServices::wrap( $services )->getQueryHelper();
		}
		$growthServices = GrowthExperimentsServices::wrap( $services );

		return new ComputedUserImpactLookup(
			new ServiceOptions( ComputedUserImpactLookup::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getDBLoadBalancerFactory(),
			$services->getChangeTagDefStore(),
			$services->getUserFactory(),
			$services->getUserEditTracker(),
			$services->getTitleFormatter(),
			$services->getTitleFactory(),
			$services->getStatsFactory(),
			$growthServices->getTaskTypeHandlerRegistry(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			$services->hasService( 'PageImages.PageImages' ) ?
			$services->getService( 'PageImages.PageImages' ) : null,
			$pageViewInfoLoaded ? $services->get( 'PageViewService' ) : null,
			$thanksQueryHelper
		);
	},

	'GrowthExperimentsUserImpactStore' => static function (
		MediaWikiServices $services
	): UserImpactLookup {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new DatabaseUserImpactStore( $growthServices->getLoadBalancer() );
	},

	'GrowthExperimentsUserImpactFormatter' => static function (
		MediaWikiServices $services
	): UserImpactFormatter {
		return new UserImpactFormatter(
			$services->get( '_GrowthExperimentsAQSConfig' )
		);
	},

	'GrowthExperimentsUserDatabaseHelper' => static function (
		MediaWikiServices $services
	): UserDatabaseHelper {
		return new UserDatabaseHelper(
			$services->getUserFactory(),
			$services->getDBLoadBalancerFactory()
		);
	},

	'GrowthExperimentsEventGateImageSuggestionFeedbackUpdater' => static function (
		MediaWikiServices $services
	): EventGateImageSuggestionFeedbackUpdater {
		return new EventGateImageSuggestionFeedbackUpdater(
			$services->get( 'EventBus.EventBusFactory' ),
			$services->getWikiPageFactory()
		);
	},

	'GrowthExperimentsLevelingUpManager' => static function (
		MediaWikiServices $services
	): LevelingUpManager {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new LevelingUpManager(
			new ServiceOptions( LevelingUpManager::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getDBLoadBalancerFactory(),
			$services->getChangeTagDefStore(),
			$services->getUserOptionsLookup(),
			$services->getUserFactory(),
			$services->getUserEditTracker(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getUserImpactLookup(),
			$growthServices->getTaskSuggesterFactory(),
			$growthServices->getNewcomerTasksUserOptionsLookup(),
			LoggerFactory::getInstance( 'GrowthExperiments' ),
			$growthServices->getGrowthWikiConfig(),
		);
	},

];
