<?php

use CirrusSearch\CirrusSearch;
use GrowthExperiments\AqsEditInfoService;
use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use GrowthExperiments\Config\Validation\StructuredMentorListValidator;
use GrowthExperiments\Config\WikiPageConfig;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPosterFactory;
use GrowthExperiments\HelpPanel\Tips\TipNodeRenderer;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\MentorDashboard\MenteeOverview\DatabaseMenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataUpdater;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use GrowthExperiments\MentorDashboard\MenteeOverview\UncachedMenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MentorDashboardModuleRegistry;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Provider\StructuredMentorProvider;
use GrowthExperiments\Mentorship\Provider\StructuredMentorWriter;
use GrowthExperiments\Mentorship\Provider\WikitextMentorProvider;
use GrowthExperiments\Mentorship\QuitMentorshipFactory;
use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\CacheBackedImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataService;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationSubmissionLogFactory;
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
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\CirrusSearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\EventGateSearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\SearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\AddLink\ServiceLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\StaticLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\CachedSuggestionsInfo;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
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
use GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\QualityGateDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\RemoteSearchTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use GrowthExperiments\WelcomeSurveyFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\EventBus\EventBusFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [

	'GrowthExperimentsAddImageSubmissionHandler' => static function (
		MediaWikiServices $services
	): AddImageSubmissionHandler {
		$cirrusSearchFactory = static function () {
			return new CirrusSearch();
		};
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new AddImageSubmissionHandler(
			$cirrusSearchFactory,
			$geServices->getTaskSuggesterFactory(),
			$geServices->getNewcomerTasksUserOptionsLookup(),
			$geServices->getNewcomerTasksConfigurationLoader(),
			$services->getMainWANObjectCache()
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
			$services->getUserFactory()
		);
	},

	'GrowthExperimentsConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'GrowthExperiments' );
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
			$services->getMainConfig()->get( 'GEWikiConfigPageTitle' )
		);
	},

	'GrowthExperimentsEditInfoService' => static function ( MediaWikiServices $services ): EditInfoService {
		$project = $services->get( '_GrowthExperimentsAQSConfig' )->project;

		return new AqsEditInfoService(
			$services->getHttpRequestFactory(),
			$services->getMainWANObjectCache(),
			$project
		);
	},

	'GrowthExperimentsExperimentUserManager' => static function (
		MediaWikiServices $services
	): ExperimentUserManager {
		return new ExperimentUserManager(
			new ServiceOptions(
				ExperimentUserManager::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getUserOptionsManager(),
			$services->getUserOptionsLookup()
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
			$growthServices->getImageRecommendationProviderUncached()
		);
	},

	'GrowthExperimentsImageRecommendationProviderUncached' => static function (
		MediaWikiServices $services
	): ImageRecommendationProvider {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getGrowthConfig();
		return new ServiceImageRecommendationProvider(
			$services->getTitleFactory(),
			$services->getHttpRequestFactory(),
			$services->getStatsdDataFactory(),
			$config->get( 'GEImageRecommendationServiceUrl' ),
			$config->get( 'GEImageRecommendationServiceHttpProxy' ),
			'wikipedia',
			$services->getContentLanguage()->getCode(),
			$growthServices->getImageRecommendationMetadataProvider(),
			$growthServices->getAddImageSubmissionHandler(),
			null,
			$config->get( 'GEImageRecommendationServiceUseTitles' )
		);
	},

	'GrowthExperimentsLinkRecommendationHelper' => static function (
		MediaWikiServices $services
	): LinkRecommendationHelper {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$cirrusSearchFactory = static function () {
			return new CirrusSearch();
		};
		return new LinkRecommendationHelper(
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getLinkRecommendationProvider(),
			$growthServices->getLinkRecommendationStore(),
			$cirrusSearchFactory
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
			$loadBalancer->getConnectionRef( DB_REPLICA ),
			$loadBalancer->getConnectionRef( DB_PRIMARY ),
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
			$services->getDBLoadBalancer()->getConnectionRef( DB_REPLICA ),
			$services->getRevisionStore(),
			$services->getNameTableStoreFactory()->getChangeTagDef(),
			$services->getPageProps(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getSearchIndexUpdater(),
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
		$geServices = GrowthExperimentsServices::wrap( $services );
		return new DatabaseMenteeOverviewDataProvider(
			$services->getMainWANObjectCache(),
			$geServices->getMentorStore(),
			$geServices->getLoadBalancer()->getConnection( DB_REPLICA )
		);
	},

	'GrowthExperimentsMenteeOverviewDataProviderUncached' => static function (
		MediaWikiServices $services
	): MenteeOverviewDataProvider {
		$geServices = GrowthExperimentsServices::wrap( $services );
		$provider = new UncachedMenteeOverviewDataProvider(
			$geServices->getMentorStore(),
			$services->getChangeTagDefStore(),
			$services->getActorMigration(),
			$services->getUserIdentityLookup(),
			$services->getDBLoadBalancer()->getConnection( DB_REPLICA, 'vslow' )
		);
		$provider->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $provider;
	},

	'GrowthExperimentsMenteeOverviewDataUpdater' => static function (
		MediaWikiServices $services
	) {
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
		$geServices = GrowthExperimentsServices::wrap( $services );

		$mentorProviderName = $services->getMainConfig()->get( 'GEMentorProvider' );
		switch ( $mentorProviderName ) {
			case MentorProvider::PROVIDER_WIKITEXT:
				return $geServices->getMentorProviderWikitext();
			case MentorProvider::PROVIDER_STRUCTURED:
				return $geServices->getMentorProviderStructured();
			default:
				throw new InvalidArgumentException(
					'Invalid value of wgGEMentorProvider: ' . $mentorProviderName
				);
		}
	},

	'GrowthExperimentsMentorProviderWikitext' => static function (
		MediaWikiServices $services
	): WikitextMentorProvider {
		$geServices = GrowthExperimentsServices::wrap( $services );
		$wikiConfig = $geServices->getGrowthWikiConfig();

		$provider = new WikitextMentorProvider(
			$services->getMainWANObjectCache(),
			$services->getLocalServerObjectCache(),
			$geServices->getMentorWeightManager(),
			$services->getTitleFactory(),
			$services->getWikiPageFactory(),
			$services->getUserNameUtils(),
			$services->getUserIdentityLookup(),
			$services->getContentLanguage(),
			$wikiConfig->get( 'GEHomepageMentorsList' ) ?: null,
			$wikiConfig->get( 'GEHomepageManualAssignmentMentorsList' ) ?: null
		);
		$provider->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $provider;
	},

	'GrowthExperimentsMentorProviderStructured' => static function (
		MediaWikiServices $services
	): StructuredMentorProvider {
		$geServices = GrowthExperimentsServices::wrap( $services );

		$provider = new StructuredMentorProvider(
			$geServices->getWikiPageConfigLoader(),
			$services->getUserIdentityLookup(),
			$services->getUserNameUtils(),
			new DerivativeContext( RequestContext::getMain() ),
			$services->getTitleFactory()->newFromText(
				$services->getMainConfig()->get( 'GEStructuredMentorList' )
			)
		);
		$provider->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $provider;
	},

	'GrowthExperimentsMentorStatusManager' => static function (
		MediaWikiServices $services
	): MentorStatusManager {
		return new MentorStatusManager(
			$services->getUserOptionsManager(),
			$services->getUserIdentityLookup(),
			$services->getUserFactory(),
			$services->getDBLoadBalancer()->getConnectionRef( DB_REPLICA ),
			$services->getDBLoadBalancer()->getConnectionRef( DB_PRIMARY )
		);
	},

	'GrowthExperimentsMentorStore' => static function ( MediaWikiServices $services ): MentorStore {
		return GrowthExperimentsServices::wrap( $services )->getDatabaseMentorStore();
	},

	'GrowthExperimentsMentorStoreDatabase' => static function ( MediaWikiServices $services ): DatabaseMentorStore {
		$geServices = GrowthExperimentsServices::wrap( $services );
		$lb = $geServices->getLoadBalancer();

		return new DatabaseMentorStore(
			$services->getMainWANObjectCache(),
			$services->getUserFactory(),
			$services->getUserIdentityLookup(),
			$services->getJobQueueGroup(),
			$lb->getConnectionRef( DB_REPLICA ),
			$lb->getConnectionRef( DB_PRIMARY ),
			defined( 'MEDIAWIKI_JOB_RUNNER' ) ||
				$geServices->getGrowthConfig()->get( 'CommandLineMode' ) ||
				RequestContext::getMain()->getRequest()->wasPosted()
		);
	},

	'GrowthExperimentsMentorWeightManager' => static function (
		MediaWikiServices $services
	): MentorWeightManager {
		return new MentorWeightManager(
			$services->getUserOptionsManager()
		);
	},

	'GrowthExperimentsMentorWriter' => static function (
		MediaWikiServices $services
	): IMentorWriter {
		$geServices = GrowthExperimentsServices::wrap( $services );

		$writer = new StructuredMentorWriter(
			$geServices->getWikiPageConfigLoader(),
			$geServices->getWikiPageConfigWriterFactory(),
			new StructuredMentorListValidator(),
			$services->getTitleFactory()->newFromText(
				$services->getMainConfig()->get( 'GEStructuredMentorList' )
			)
		);
		$writer->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
		return $writer;
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
		if ( $topicType === PageConfigurationLoader::CONFIGURATION_TYPE_ORES ) {
			$topicConfigTitle = $config->get( 'GENewcomerTasksOresTopicConfigTitle' );
		} elseif ( $topicType === PageConfigurationLoader::CONFIGURATION_TYPE_MORELIKE ) {
			$topicConfigTitle = $config->get( 'GENewcomerTasksTopicConfigTitle' );
		}

		$configurationLoader = new PageConfigurationLoader(
			$services->getTitleFactory(),
			$growthServices->getWikiPageConfigLoader(),
			$growthServices->getNewcomerTasksConfigurationValidator(),
			$growthServices->getTaskTypeHandlerRegistry(),
			$taskConfigTitle,
			$topicConfigTitle,
			$topicType
		);

		if ( !$config->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) ) {
			$configurationLoader->disableTaskType( LinkRecommendationTaskTypeHandler::TASK_TYPE_ID );
		}
		if ( !$config->get( 'GENewcomerTasksImageRecommendationsEnabled' ) ) {
			$configurationLoader->disableTaskType( ImageRecommendationTaskTypeHandler::TASK_TYPE_ID );
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
			$services->getCollationFactory()->getCategoryCollation(),
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
			$services->getRestrictionStore()
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
			$services->getPerDbNameStatsdDataFactory()
		);
	},

	'GrowthExperimentsQuitMentorshipFactory' => static function (
		MediaWikiServices $services
	): QuitMentorshipFactory {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new QuitMentorshipFactory(
			$growthServices->getMentorManager(),
			$growthServices->getMentorProvider(),
			$growthServices->getMentorStore(),
			$growthServices->getChangeMentorFactory(),
			$services->getPermissionManager(),
			$services->getJobQueueGroupFactory()
		);
	},

	'GrowthExperimentsSearchIndexUpdater' => static function (
		MediaWikiServices $services
	): SearchIndexUpdater {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		if ( $growthServices->getGrowthConfig()->get( 'GELinkRecommendationsUseEventGate' ) ) {
			/** @var EventBusFactory $eventBusFactory */
			$eventBusFactory = $services->get( 'EventBus.EventBusFactory' );
			return new EventGateSearchIndexUpdater( $eventBusFactory );
		} else {
			return new CirrusSearchIndexUpdater();
		}
	},

	'GrowthExperimentsStarredMenteesStore' => static function (
		MediaWikiServices $services
	): StarredMenteesStore {
		return new StarredMenteesStore(
			$services->getUserFactory(),
			$services->getUserIdentityLookup(),
			$services->getUserOptionsManager()
		);
	},

	'GrowthExperimentsTaskSuggesterFactory' => static function (
		MediaWikiServices $services
	): TaskSuggesterFactory {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$config = $growthServices->getGrowthConfig();

		$taskTypeHandlerRegistry = $growthServices->getTaskTypeHandlerRegistry();
		$configLoader = $growthServices->getNewcomerTasksConfigurationLoader();
		$searchStrategy = new SearchStrategy( $taskTypeHandlerRegistry );
		if ( $config->get( 'GENewcomerTasksRemoteApiUrl' ) ) {
			$taskSuggesterFactory = new RemoteSearchTaskSuggesterFactory(
				$taskTypeHandlerRegistry,
				$configLoader,
				$searchStrategy,
				$growthServices->getNewcomerTasksUserOptionsLookup(),
				$services->getHttpRequestFactory(),
				$services->getTitleFactory(),
				$services->getLinkBatchFactory(),
				$config->get( 'GENewcomerTasksRemoteApiUrl' )
			);
		} else {
			$taskSuggesterFactory = new LocalSearchTaskSuggesterFactory(
				$taskTypeHandlerRegistry,
				$configLoader,
				$searchStrategy,
				$growthServices->getNewcomerTasksUserOptionsLookup(),
				$services->getSearchEngineFactory(),
				$services->getLinkBatchFactory(),
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
							new TaskSetListener( $services->getMainWANObjectCache() ),
							$services->getJsonCodec()
						],
					],
					[
						'class' => QualityGateDecorator::class,
						'args' => [
							$growthServices->getNewcomerTasksConfigurationLoader(),
							$growthServices->getImageRecommendationSubmissionLogFactory(),
							$growthServices->getLinkRecommendationSubmissionLogFactory(),
							$growthServices->getGrowthExperimentsCampaignConfig()
						]
					],
				]
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
			$services->getUserOptionsManager()
		);
	},

	'GrowthExperimentsWikiPageConfigLoader' => static function (
		MediaWikiServices $services
	): WikiPageConfigLoader {
		$wikiPageConfigLoader = new WikiPageConfigLoader(
			GrowthExperimentsServices::wrap( $services )
				->getWikiPageConfigValidatorFactory(),
			$services->getHttpRequestFactory(),
			$services->getRevisionLookup(),
			$services->getTitleFactory()
		);

		// Cache config for a day; cache is invalidated by ConfigHooks::onPageSaveComplete
		// and WikiPageConfigWriter::save when config files are changed.
		$wikiPageConfigLoader->setCache(
			new CachedBagOStuff( ObjectCache::getLocalClusterInstance() ),
			CachedBagOStuff::TTL_DAY
		);
		return $wikiPageConfigLoader;
	},

	'GrowthExperimentsWikiPageConfigWriterFactory' => static function (
		MediaWikiServices $services
	) {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new WikiPageConfigWriterFactory(
			$growthExperimentsServices->getWikiPageConfigLoader(),
			$growthExperimentsServices->getWikiPageConfigValidatorFactory(),
			$services->getWikiPageFactory(),
			$services->getTitleFactory(),
			$services->getUserFactory(),
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
			$services->getContentLanguage()->getCode()
		);
	},

	'GrowthExperimentsImageRecommendationMetadataProvider' => static function (
		MediaWikiServices $services
	): ImageRecommendationMetadataProvider {
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		return new ImageRecommendationMetadataProvider(
			$growthExperimentsServices->getImageRecommendationMetadataService(),
			$services->getContentLanguage()->getCode(),
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
			$services->getUserOptionsLookup()
		);
	},

	'GrowthExperimentsLinkRecommendationSubmissionLogFactory' => static function (
		MediaWikiServices $services
	): LinkRecommendationSubmissionLogFactory {
		return new LinkRecommendationSubmissionLogFactory(
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
			$services->getPerDbNameStatsdDataFactory(),
			$services->getRevisionLookup(),
			$services->getDBLoadBalancer()
		);
	},

];
