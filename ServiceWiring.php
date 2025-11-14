<?php

use CirrusSearch\CirrusSearchServices;
use GrowthExperiments\AbstractExperimentManager;
use GrowthExperiments\Config\MediaWikiConfigReaderWrapper;
use GrowthExperiments\EventLogging\GrowthExperimentsInteractionLogger;
use GrowthExperiments\EventLogging\PersonalizedPraiseLogger;
use GrowthExperiments\EventLogging\ReviseToneExperimentInteractionLogger;
use GrowthExperiments\ExperimentUserDefaultsManager;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\ExperimentXLabManager;
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
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\MenteeGraduation;
use GrowthExperiments\Mentorship\MenteeGraduationProcessor;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\MentorRemover;
use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorProvider;
use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorWriter;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
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
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\LinkRecommendationFilter;
use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use GrowthExperiments\NewcomerTasks\NewcomerTasksInfo;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use GrowthExperiments\NewcomerTasks\ReviseTone\ApiReviseToneRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ReviseTone\NullReviseToneRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ReviseTone\SubpageReviseToneRecommendationProvider;
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
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeManager;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\StaticTopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\WikimediaTopicRegistry;
use GrowthExperiments\PeriodicMetrics\MetricsFactory;
use GrowthExperiments\UserDatabaseHelper;
use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use GrowthExperiments\UserImpact\DatabaseUserImpactStore;
use GrowthExperiments\UserImpact\GrowthExperimentsUserImpactUpdater;
use GrowthExperiments\UserImpact\SubpageUserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactFormatter;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\Util;
use GrowthExperiments\WelcomeSurveyFactory;
use MediaWiki\Api\ApiRawMessage;
use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Extension\Thanks\ThanksServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;

/** @phpcs-require-sorted-array */
return [

	'GrowthExperimentsAddImageSubmissionHandler' => static function (
		MediaWikiServices $services
	): AddImageSubmissionHandler {
		$geServices = GrowthExperimentsServices::wrap( $services );
		if ( Util::areImageRecommendationDependenciesSatisfied() ) {
			$cirrusSearchServices = CirrusSearchServices::wrap( $services );
			$weightedTagsUpdater = $cirrusSearchServices->getWeightedTagsUpdater();
		} else {
			$weightedTagsUpdater = null;
		}

		return new AddImageSubmissionHandler(
			$weightedTagsUpdater,
			$geServices->getTaskSuggesterFactory(),
			$geServices->getNewcomerTasksUserOptionsLookup(),
			$services->getMainWANObjectCache(),
			$services->getUserIdentityUtils(),
			$geServices->getEventGateImageSuggestionFeedbackUpdater(),
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
			$growthServices->getLogger()
		);
	},

	'GrowthExperimentsCampaignConfig' => static function (
		MediaWikiServices $services
	): CampaignConfig {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new CampaignConfig(
			$growthServices->getGrowthConfig()->get( 'GECampaigns' ) ?? [],
			$growthServices->getGrowthConfig()->get( 'GECampaignTopics' ) ?? [],
			$services->getUserOptionsLookup()
		);
	},

	'GrowthExperimentsChangeMentorFactory' => static function (
		MediaWikiServices $services
	): ChangeMentorFactory {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new ChangeMentorFactory(
			$geServices->getLogger(),
			$geServices->getMentorManager(),
			$geServices->getMentorStore(),
			$services->getUserFactory(),
			$services->getDBLoadBalancerFactory()
		);
	},

	'GrowthExperimentsCommunityConfig' => static function ( MediaWikiServices $services ): Config {
		return new MediaWikiConfigReaderWrapper(
			$services->get( 'CommunityConfiguration.MediaWikiConfigRouter' )
		);
	},

	'GrowthExperimentsConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
	},

	'GrowthExperimentsEventGateImageSuggestionFeedbackUpdater' => static function (
		MediaWikiServices $services
	): ?EventGateImageSuggestionFeedbackUpdater {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventBus' ) ) {
			return null;
		}

		return new EventGateImageSuggestionFeedbackUpdater(
			$services->get( 'EventBus.EventBusFactory' ),
			$services->getWikiPageFactory()
		);
	},

	'GrowthExperimentsExperimentUserDefaultsManager' => static function (
		MediaWikiServices $services
	): ExperimentUserDefaultsManager {
		return new ExperimentUserDefaultsManager(
			GrowthExperimentsServices::wrap( $services )->getLogger(),
			static function () use ( $services ) {
				return $services->getCentralIdLookup();
			},
			$services->getUserIdentityUtils()
		);
	},

	'GrowthExperimentsExperimentUserManager' => static function (
		MediaWikiServices $services
	): AbstractExperimentManager {
		if ( Util::useMetricsPlatform() ) {
			return new ExperimentXLabManager(
				new ServiceOptions(
					ExperimentXLabManager::CONSTRUCTOR_OPTIONS,
					$services->getMainConfig()
				),
				GrowthExperimentsServices::wrap( $services )->getLogger(),
				$services->getService( 'MetricsPlatform.ConfigsFetcher' ),
				$services->getService( 'MetricsPlatform.XLab.EnrollmentAuthority' ),
				$services->getService( 'MetricsPlatform.XLab.ExperimentManager' ),
				$services->getMainConfig(),
			);
		}
		return new ExperimentUserManager(
			GrowthExperimentsServices::wrap( $services )->getLogger(),
			new ServiceOptions(
				ExperimentUserManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getUserOptionsManager(),
			$services->getUserOptionsLookup(),
			$services->getUserFactory()
		);
	},

	'GrowthExperimentsHomepageModuleRegistry' => static function (
		MediaWikiServices $services
	): HomepageModuleRegistry {
		return new HomepageModuleRegistry( $services );
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

	'GrowthExperimentsImageRecommendationFilter' => static function (
		MediaWikiServices $services
	): ImageRecommendationFilter {
		return new ImageRecommendationFilter(
			$services->getMainWANObjectCache()
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

	'GrowthExperimentsImageRecommendationSubmissionLogFactory' => static function (
		MediaWikiServices $services
	): ImageRecommendationSubmissionLogFactory {
		return new ImageRecommendationSubmissionLogFactory(
			$services->getDBLoadBalancerFactory(),
			$services->getUserOptionsLookup()
		);
	},

	'GrowthExperimentsInteractionLogger' => static function (
		MediaWikiServices $services
	): GrowthExperimentsInteractionLogger {
		return new GrowthExperimentsInteractionLogger();
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
			$services->getJobQueueGroup(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getUserImpactLookup(),
			$growthServices->getTaskSuggesterFactory(),
			$growthServices->getNewcomerTasksUserOptionsLookup(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getLogger(),
			$growthServices->getGrowthWikiConfig(),
		);
	},

	'GrowthExperimentsLinkRecommendationFilter' => static function (
		MediaWikiServices $services
	): LinkRecommendationFilter {
		return new LinkRecommendationFilter(
			GrowthExperimentsServices::wrap( $services )->getLinkRecommendationStore()
		);
	},

	'GrowthExperimentsLinkRecommendationHelper' => static function (
		MediaWikiServices $services
	): LinkRecommendationHelper {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		if ( Util::isLinkRecommendationsAvailable() ) {
			$cirrusSearchServices = CirrusSearchServices::wrap( $services );
			$weightedTagsUpdater = $cirrusSearchServices->getWeightedTagsUpdater();
		} else {
			$weightedTagsUpdater = null;
		}
		return new LinkRecommendationHelper(
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getLinkRecommendationProvider(),
			$growthServices->getLinkRecommendationStore(),
			$weightedTagsUpdater
		);
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

	'GrowthExperimentsLinkRecommendationStore' => static function (
		MediaWikiServices $services
	): LinkRecommendationStore {
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new LinkRecommendationStore(
			$services->getConnectionProvider(),
			$geServices->getLoadBalancer(),
			$services->getTitleFactory(),
			$services->getLinkBatchFactory(),
			$services->getPageStore(),
			$geServices->getLogger()
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

	'GrowthExperimentsLinkRecommendationUpdater' => static function (
		MediaWikiServices $services
	): LinkRecommendationUpdater {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$cirrusSearchServices = CirrusSearchServices::wrap( $services );
		return new LinkRecommendationUpdater(
			$growthServices->getLogger(),
			$services->getDBLoadBalancerFactory(),
			$services->getRevisionStore(),
			$services->getNameTableStoreFactory()->getChangeTagDef(),
			$services->getPageProps(),
			$services->getChangeTagsStore(),
			$services->getWikiPageFactory(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$cirrusSearchServices->getWeightedTagsUpdater(),
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

	'GrowthExperimentsLogger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'GrowthExperiments' );
	},

	'GrowthExperimentsMenteeGraduation' => static function (
		MediaWikiServices $services
	): MenteeGraduation {
		$geServices = GrowthExperimentsServices::wrap( $services );

		return new MenteeGraduation(
			$geServices->getGrowthWikiConfig(),
			$services->getUserEditTracker(),
			$services->getUserRegistrationLookup(),
			$geServices->getMentorManager()
		);
	},

	'GrowthExperimentsMenteeGraduationProcessor' => static function (
		MediaWikiServices $services
	): MenteeGraduationProcessor {
		$geServices = GrowthExperimentsServices::wrap( $services );

		return new MenteeGraduationProcessor(
			$geServices->getLogger(),
			$geServices->getMentorStore(),
			$geServices->getMenteeGraduation()
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
		return new UncachedMenteeOverviewDataProvider(
			$geServices->getMentorStore(),
			$services->getChangeTagDefStore(),
			$services->getActorMigration(),
			$services->getUserIdentityLookup(),
			$services->getTempUserConfig(),
			$services->getDBLoadBalancerFactory(),
			$geServices->getLogger()
		);
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
	): IMentorManager {
		$geServices = GrowthExperimentsServices::wrap( $services );

		return new MentorManager(
			$geServices->getMentorStore(),
			$geServices->getMentorStatusManager(),
			$geServices->getMentorProvider(),
			$services->getUserFactory(),
			$services->getUserOptionsLookup(),
			$services->getUserOptionsManager(),
			$geServices->getLogger(),
		);
	},

	'GrowthExperimentsMentorProvider' => static function (
		MediaWikiServices $services
	): MentorProvider {
		return GrowthExperimentsServices::wrap( $services )
			->getMentorProviderStructured();
	},

	'GrowthExperimentsMentorProviderStructured' => static function (
		MediaWikiServices $services
	): CommunityStructuredMentorProvider {
		return new CommunityStructuredMentorProvider(
			GrowthExperimentsServices::wrap( $services )->getLogger(),
			$services->getUserIdentityLookup(),
			new DerivativeContext( RequestContext::getMain() ),
			CommunityConfigurationServices::wrap( $services )
				->getConfigurationProviderFactory()->newProvider( 'GrowthMentorList' ),
			$services->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			$services->getMainWANObjectCache()
		);
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
			$services->getDBLoadBalancerFactory(),
		);
	},

	'GrowthExperimentsMentorStore' => static function ( MediaWikiServices $services ): MentorStore {
		return GrowthExperimentsServices::wrap( $services )->getDatabaseMentorStore();
	},

	'GrowthExperimentsMentorStoreDatabase' => static function ( MediaWikiServices $services ): DatabaseMentorStore {
		$geServices = GrowthExperimentsServices::wrap( $services );
		$lb = $geServices->getLoadBalancer();

		return new DatabaseMentorStore(
			$geServices->getLogger(),
			$services->getMainWANObjectCache(),
			$services->getUserFactory(),
			$services->getUserIdentityLookup(),
			$services->getJobQueueGroup(),
			$lb,
			defined( 'MEDIAWIKI_JOB_RUNNER' ) ||
				MW_ENTRY_POINT === 'cli' ||
				RequestContext::getMain()->getRequest()->wasPosted()
		);
	},

	'GrowthExperimentsMentorWriter' => static function (
		MediaWikiServices $services
	): IMentorWriter {
		$geServices = GrowthExperimentsServices::wrap( $services );

		$writer = new CommunityStructuredMentorWriter(
			$geServices->getLogger(),
			$geServices->getMentorProvider(),
			$services->getUserIdentityLookup(),
			$services->getUserFactory(),
			$services->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
			CommunityConfigurationServices::wrap( $services )
				->getConfigurationProviderFactory()->newProvider( 'GrowthMentorList' ),
			$geServices->getMentorStatusManager(),
		);
		return $writer;
	},

	'GrowthExperimentsMetricsFactory' => static function (
		MediaWikiServices $services
	): MetricsFactory {
		return new MetricsFactory(
			$services->getDBLoadBalancer(),
			$services->getUserEditTracker(),
			GrowthExperimentsServices::wrap( $services )->getMentorProvider(),
		);
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

	'GrowthExperimentsNewcomerTasksConfigurationLoader' => static function (
		MediaWikiServices $services
	): ConfigurationLoader {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getGrowthConfig();
		if ( !$config->get( 'GEHomepageSuggestedEditsEnabled' ) ) {
			return new ErrorForwardingConfigurationLoader(
				StatusValue::newFatal(
					'rawmessage',
					'Task types are unavailable because Suggested edits is not enabled, ' .
					'see GEHomepageSuggestedEditsEnabled.'
				),
				$growthServices->getLogger()
			);
		}

		$providerFactory = CommunityConfigurationServices::wrap( $services )
			->getConfigurationProviderFactory();
		$suggestedEditsProvider = in_array( 'GrowthSuggestedEdits', $providerFactory->getSupportedKeys() ) ?
			$providerFactory->newProvider( 'GrowthSuggestedEdits' ) : null;

		$configurationLoader = new CommunityConfigurationLoader(
			$growthServices->getTaskTypeHandlerRegistry(),
			$suggestedEditsProvider,
			$growthServices->getLogger(),
		);

		if ( !$config->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) ) {
			$configurationLoader->disableTaskType( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}
		if ( !$config->get( 'GENewcomerTasksImageRecommendationsEnabled' ) ) {
			$configurationLoader->disableTaskType( ImageRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}
		if ( !$config->get( 'GENewcomerTasksSectionImageRecommendationsEnabled' ) ) {
			$configurationLoader->disableTaskType( SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}

		return $configurationLoader;
	},

	'GrowthExperimentsNewcomerTasksConfigurationValidator' => static function (
		MediaWikiServices $services
	): ConfigurationValidator {
		return new ConfigurationValidator( RequestContext::getMain() );
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
			$geServices->getLogger(),
			$services->getMainObjectStash(),
			$services->getUserOptionsManager(),
			$geServices->getPraiseworthyConditionsLookup(),
			$geServices->getPersonalizedPraiseNotificationsDispatcher(),
			$geServices->getPersonalizedPraiseLogger(),
			$geServices->getMentorStore(),
			$geServices->getUserImpactStore()
		);
		return $suggester;
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

	'GrowthExperimentsQuestionPosterFactory' => static function (
		MediaWikiServices $services
	): QuestionPosterFactory {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new QuestionPosterFactory(
			$services->getWikiPageFactory(),
			$services->getTitleFactory(),
			$growthServices->getMentorManager(),
			$growthServices->getMentorStatusManager(),
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

	'GrowthExperimentsReviseToneExperimentInteractionLogger' => static function (
		MediaWikiServices $services
	): ReviseToneExperimentInteractionLogger {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$eventLoggingMetricsClientFactory = null;
		if ( $services->has( 'EventLogging.MetricsClientFactory' ) ) {
			$eventLoggingMetricsClientFactory = $services->get( 'EventLogging.MetricsClientFactory' );
		}
		return new ReviseToneExperimentInteractionLogger(
			$growthServices->getExperimentUserManager(),
			$eventLoggingMetricsClientFactory
		);
	},

	'GrowthExperimentsReviseToneRecommendationProvider' => static function (
		MediaWikiServices $services
	): RecommendationProvider {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$growthConfig = $growthServices->getGrowthConfig();
		$providerType = $growthConfig->get( 'GEReviseToneRecommendationProvider' );
		if ( $providerType === 'subpage' ) {
			return new SubpageReviseToneRecommendationProvider(
				$services->getWikiPageFactory(),
				new NullReviseToneRecommendationProvider()
			);
		}
		if ( $providerType === 'production' ) {
			return new ApiReviseToneRecommendationProvider(
				$growthConfig->get( 'GEReviseToneServiceUrl' ),
				WikiMap::getCurrentWikiId(),
				$services->getTitleFactory(),
				$services->getHttpRequestFactory(),
				$growthServices->getLogger(),
				$services->getStatsFactory(),
			);
		}
		$growthServices->getLogger()->error(
			'Invalid value for GEReviseToneRecommendationProvider config: {configured_value}',
			[
				'configured_value' => $providerType,
			],
		);
		return new NullReviseToneRecommendationProvider();
	},

	'GrowthExperimentsSectionImageRecommendationSubmissionLogFactory' => static function (
		MediaWikiServices $services
	): SectionImageRecommendationSubmissionLogFactory {
		return new SectionImageRecommendationSubmissionLogFactory(
			$services->getDBLoadBalancerFactory(),
			$services->getUserOptionsLookup()
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

	'GrowthExperimentsSuggestionsInfo' => static function (
		MediaWikiServices $services
	): NewcomerTasksInfo {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new CachedSuggestionsInfo(
			new SuggestionsInfo(
				$growthServices->getTaskSuggesterFactory(),
				$growthServices->getTaskTypeHandlerRegistry(),
				$growthServices->getNewcomerTasksConfigurationLoader(),
				$growthServices->getTopicRegistry()
			),
			$services->getMainWANObjectCache()
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
				$growthConfig->get( 'GENewcomerTasksRemoteApiUrl' ),
				$growthServices->getTopicRegistry()
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
				$growthServices->getTopicRegistry()
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
							$services->getJsonCodec(),
						],
					],
					[
						'class' => QualityGateDecorator::class,
						'args' => [
							$growthServices->getNewcomerTasksConfigurationLoader(),
							$growthServices->getImageRecommendationSubmissionLogFactory(),
							$growthServices->getSectionImageRecommendationSubmissionLogFactory(),
							$growthServices->getLinkRecommendationSubmissionLogFactory(),
							$growthServices->getGrowthExperimentsCampaignConfig(),
						],
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
		$taskSuggesterFactory->setLogger( $growthServices->getLogger() );
		return $taskSuggesterFactory;
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

	'GrowthExperimentsTaskTypeManager' => static function (
		MediaWikiServices $services
	): TaskTypeManager {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new TaskTypeManager(
			$services->getMainConfig(),
			$growthServices->getNewcomerTasksUserOptionsLookup(),
			$services->getUserEditTracker(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getLevelingUpManager()
		);
	},

	'GrowthExperimentsTemplateBasedTaskSubmissionHandler' => static function (
		MediaWikiServices $services
	): TemplateBasedTaskSubmissionHandler {
		return new TemplateBasedTaskSubmissionHandler();
	},

	'GrowthExperimentsTipNodeRenderer' => static function (
		MediaWikiServices $services
	): TipNodeRenderer {
		return new TipNodeRenderer(
			$services->getMainConfig()->get( 'ExtensionAssetsPath' )
		);
	},

	'GrowthExperimentsTipsAssembler' => static function (
		MediaWikiServices $services
	): TipsAssembler {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new TipsAssembler(
			$growthExperimentsServices->getTipNodeRenderer()
		);
	},

	'GrowthExperimentsTopicRegistry' => static function (
		MediaWikiServices $services
	): ITopicRegistry {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'WikimediaMessages' ) ) {
			$growthServices = GrowthExperimentsServices::wrap( $services );
			$registry = new WikimediaTopicRegistry(
				RequestContext::getMain(),
				$services->getCollationFactory()
			);
			$registry->setCampaignConfigCallback( static function () use ( $growthServices ) {
				return $growthServices->getGrowthExperimentsCampaignConfig();
			} );

			return $registry;
		}
		return new StaticTopicRegistry();
	},

	'GrowthExperimentsUserDatabaseHelper' => static function (
		MediaWikiServices $services
	): UserDatabaseHelper {
		return new UserDatabaseHelper(
			$services->getUserFactory(),
			$services->getDBLoadBalancerFactory()
		);
	},

	'GrowthExperimentsUserImpactFormatter' => static function (
		MediaWikiServices $services
	): UserImpactFormatter {
		return new UserImpactFormatter(
			$services->get( '_GrowthExperimentsAQSConfig' )
		);
	},

	'GrowthExperimentsUserImpactLookup' => static function (
		MediaWikiServices $services
	): UserImpactLookup {
		return $services->get( 'GrowthExperimentsUserImpactLookup_Computed' );
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
			$growthServices->getLogger(),
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

	'GrowthExperimentsUserImpactUpdater' => static function (
		MediaWikiServices $services
	): GrowthExperimentsUserImpactUpdater {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new GrowthExperimentsUserImpactUpdater(
			$services->getUserEditTracker(),
			$services->getUserFactory(),
			$services->getJobQueueGroup(),
			$growthServices->getUncachedUserImpactLookup(),
			$growthServices->getUserImpactStore(),
			$growthServices->getUserImpactFormatter()
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

	'_GrowthExperimentsUserImpactLookup_Subpage' => static function (
		MediaWikiServices $services
	): UserImpactLookup {
		return new SubpageUserImpactLookup(
			$services->getWikiPageFactory()
		);
	},

];
