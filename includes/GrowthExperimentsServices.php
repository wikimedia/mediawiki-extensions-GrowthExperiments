<?php

namespace GrowthExperiments;

use Config;
use GrowthExperiments\Config\Validation\ConfigValidatorFactory;
use GrowthExperiments\Config\WikiPageConfig;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPosterFactory;
use GrowthExperiments\HelpPanel\Tips\TipNodeRenderer;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataProvider;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataUpdater;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use GrowthExperiments\MentorDashboard\MentorDashboardModuleRegistry;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\ChangeMentorFactory;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Provider\StructuredMentorProvider;
use GrowthExperiments\Mentorship\Provider\WikitextMentorProvider;
use GrowthExperiments\Mentorship\QuitMentorshipFactory;
use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationApiHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationMetadataService;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationSubmissionLogFactory;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater;
use GrowthExperiments\NewcomerTasks\AddLink\LinkSubmissionRecorder;
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\SearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\LinkRecommendationFilter;
use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\SuggestionsInfo;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * A simple wrapper for MediaWikiServices, to support type safety when accessing
 * services defined by this extension.
 */
class GrowthExperimentsServices {

	/** @var MediaWikiServices */
	private $coreServices;

	/**
	 * @param MediaWikiServices $coreServices
	 */
	public function __construct( MediaWikiServices $coreServices ) {
		$this->coreServices = $coreServices;
	}

	/**
	 * Static version of the constructor, for nicer syntax.
	 * @param MediaWikiServices $coreServices
	 * @return static
	 */
	public static function wrap( MediaWikiServices $coreServices ) {
		return new static( $coreServices );
	}

	// Service aliases
	// phpcs:disable MediaWiki.Commenting.FunctionComment

	public function getChangeMentorFactory(): ChangeMentorFactory {
		return $this->coreServices->get( 'GrowthExperimentsChangeMentorFactory' );
	}

	public function getGrowthConfig(): Config {
		return $this->coreServices->get( 'GrowthExperimentsConfig' );
	}

	public function getGrowthWikiConfig(): Config {
		return $this->coreServices->get( 'GrowthExperimentsMultiConfig' );
	}

	public function getLoadBalancer(): ILoadBalancer {
		$databaseCluster = $this->getGrowthConfig()->get( 'GEDatabaseCluster' );
		if ( $databaseCluster ) {
			return $this->coreServices->getDBLoadBalancerFactory()->getExternalLB( $databaseCluster );
		} else {
			return $this->coreServices->getDBLoadBalancerFactory()->getMainLB();
		}
	}

	public function getEditInfoService(): EditInfoService {
		return $this->coreServices->get( 'GrowthExperimentsEditInfoService' );
	}

	public function getExperimentUserManager(): ExperimentUserManager {
		return $this->coreServices->get( 'GrowthExperimentsExperimentUserManager' );
	}

	public function getHomepageModuleRegistry(): HomepageModuleRegistry {
		return $this->coreServices->get( 'GrowthExperimentsHomepageModuleRegistry' );
	}

	public function getImageRecommendationProvider(): ImageRecommendationProvider {
		return $this->coreServices->get( 'GrowthExperimentsImageRecommendationProvider' );
	}

	public function getImageRecommendationProviderUncached(): ImageRecommendationProvider {
		return $this->coreServices->get( 'GrowthExperimentsImageRecommendationProviderUncached' );
	}

	public function getLinkRecommendationHelper(): LinkRecommendationHelper {
		return $this->coreServices->get( 'GrowthExperimentsLinkRecommendationHelper' );
	}

	public function getLinkRecommendationProvider(): LinkRecommendationProvider {
		return $this->coreServices->get( 'GrowthExperimentsLinkRecommendationProvider' );
	}

	public function getLinkRecommendationStore(): LinkRecommendationStore {
		return $this->coreServices->get( 'GrowthExperimentsLinkRecommendationStore' );
	}

	public function getLinkRecommendationUpdater(): LinkRecommendationUpdater {
		return $this->coreServices->get( 'GrowthExperimentsLinkRecommendationUpdater' );
	}

	public function getLinkSubmissionRecorder(): LinkSubmissionRecorder {
		return $this->coreServices->get( 'GrowthExperimentsLinkSubmissionRecorder' );
	}

	public function getMenteeOverviewDataProvider(): MenteeOverviewDataProvider {
		return $this->coreServices->get( 'GrowthExperimentsMenteeOverviewDataProvider' );
	}

	public function getMenteeOverviewDataUpdater(): MenteeOverviewDataUpdater {
		return $this->coreServices->get( 'GrowthExperimentsMenteeOverviewDataUpdater' );
	}

	public function getUncachedMenteeOverviewDataProvider(): MenteeOverviewDataProvider {
		return $this->coreServices->get( 'GrowthExperimentsMenteeOverviewDataProviderUncached' );
	}

	public function getMentorDashboardModuleRegistry(): MentorDashboardModuleRegistry {
		return $this->coreServices->get( 'GrowthExperimentsMentorDashboardModuleRegistry' );
	}

	public function getMentorManager(): MentorManager {
		return $this->coreServices->get( 'GrowthExperimentsMentorManager' );
	}

	public function getMentorProvider(): MentorProvider {
		return $this->coreServices->get( 'GrowthExperimentsMentorProvider' );
	}

	public function getMentorProviderStructured(): StructuredMentorProvider {
		return $this->coreServices->get( 'GrowthExperimentsMentorProviderStructured' );
	}

	public function getMentorProviderWikitext(): WikitextMentorProvider {
		return $this->coreServices->get( 'GrowthExperimentsMentorProviderWikitext' );
	}

	public function getMentorStatusManager(): MentorStatusManager {
		return $this->coreServices->get( 'GrowthExperimentsMentorStatusManager' );
	}

	public function getMentorStore(): MentorStore {
		return $this->coreServices->get( 'GrowthExperimentsMentorStore' );
	}

	public function getMentorWeightManager(): MentorWeightManager {
		return $this->coreServices->get( 'GrowthExperimentsMentorWeightManager' );
	}

	public function getMentorWriter(): IMentorWriter {
		return $this->coreServices->get( 'GrowthExperimentsMentorWriter' );
	}

	public function getDatabaseMentorStore(): DatabaseMentorStore {
		return $this->coreServices->get( 'GrowthExperimentsMentorStoreDatabase' );
	}

	public function getNewcomerTasksConfigurationLoader(): ConfigurationLoader {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTasksConfigurationLoader' );
	}

	public function getNewcomerTasksConfigurationValidator(): ConfigurationValidator {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTasksConfigurationValidator' );
	}

	public function getNewcomerTasksUserOptionsLookup(): NewcomerTasksUserOptionsLookup {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTasksUserOptionsLookup' );
	}

	public function getProtectionFilter(): ProtectionFilter {
		return $this->coreServices->get( 'GrowthExperimentsProtectionFilter' );
	}

	public function getLinkRecommendationFilter(): LinkRecommendationFilter {
		return $this->coreServices->get( 'GrowthExperimentsLinkRecommendationFilter' );
	}

	public function getImageRecommendationFilter(): ImageRecommendationFilter {
		return $this->coreServices->get( 'GrowthExperimentsImageRecommendationFilter' );
	}

	public function getQuestionPosterFactory(): QuestionPosterFactory {
		return $this->coreServices->get( 'GrowthExperimentsQuestionPosterFactory' );
	}

	public function getQuitMentorshipFactory(): QuitMentorshipFactory {
		return $this->coreServices->get( 'GrowthExperimentsQuitMentorshipFactory' );
	}

	public function getSearchIndexUpdater(): SearchIndexUpdater {
		return $this->coreServices->get( 'GrowthExperimentsSearchIndexUpdater' );
	}

	public function getStarredMenteesStore(): StarredMenteesStore {
		return $this->coreServices->get( 'GrowthExperimentsStarredMenteesStore' );
	}

	public function getSuggestionsInfo(): SuggestionsInfo {
		return $this->coreServices->get( 'GrowthExperimentsSuggestionsInfo' );
	}

	public function getTaskSuggesterFactory(): TaskSuggesterFactory {
		return $this->coreServices->get( 'GrowthExperimentsTaskSuggesterFactory' );
	}

	public function getTaskTypeHandlerRegistry(): TaskTypeHandlerRegistry {
		return $this->coreServices->get( 'GrowthExperimentsTaskTypeHandlerRegistry' );
	}

	public function getTipsAssembler(): TipsAssembler {
		return $this->coreServices->get( 'GrowthExperimentsTipsAssembler' );
	}

	public function getTipNodeRenderer(): TipNodeRenderer {
		return $this->coreServices->get( 'GrowthExperimentsTipNodeRenderer' );
	}

	public function getWelcomeSurveyFactory(): WelcomeSurveyFactory {
		return $this->coreServices->get( 'GrowthExperimentsWelcomeSurveyFactory' );
	}

	public function getWikiPageConfig(): WikiPageConfig {
		return $this->coreServices->get( 'GrowthExperimentsWikiPageConfig' );
	}

	public function getWikiPageConfigLoader(): WikiPageConfigLoader {
		return $this->coreServices->get( 'GrowthExperimentsWikiPageConfigLoader' );
	}

	public function getWikiPageConfigValidatorFactory(): ConfigValidatorFactory {
		return $this->coreServices->get( 'GrowthExperimentsConfigValidatorFactory' );
	}

	public function getWikiPageConfigWriterFactory(): WikiPageConfigWriterFactory {
		return $this->coreServices->get( 'GrowthExperimentsWikiPageConfigWriterFactory' );
	}

	public function getImageRecommendationMetadataService(): ImageRecommendationMetadataService {
		return $this->coreServices->get( 'GrowthExperimentsImageRecommendationMetadataService' );
	}

	public function getImageRecommendationMetadataProvider(): ImageRecommendationMetadataProvider {
		return $this->coreServices->get( 'GrowthExperimentsImageRecommendationMetadataProvider' );
	}

	public function getImageRecommendationSubmissionLogFactory(): ImageRecommendationSubmissionLogFactory {
		return $this->coreServices->get( 'GrowthExperimentsImageRecommendationSubmissionLogFactory' );
	}

	public function getLinkRecommendationSubmissionLogFactory(): LinkRecommendationSubmissionLogFactory {
		return $this->coreServices->get( 'GrowthExperimentsLinkRecommendationSubmissionLogFactory' );
	}

	public function getAddImageSubmissionHandler(): AddImageSubmissionHandler {
		return $this->coreServices->get( 'GrowthExperimentsAddImageSubmissionHandler' );
	}

	public function getGrowthExperimentsCampaignConfig(): CampaignConfig {
		return $this->coreServices->get( 'GrowthExperimentsCampaignConfig' );
	}

	public function getTemplateBasedTaskSubmissionHandler(): TemplateBasedTaskSubmissionHandler {
		return $this->coreServices->get( 'GrowthExperimentsTemplateBasedTaskSubmissionHandler' );
	}

	public function getNewcomerTasksChangeTagsManager(): NewcomerTasksChangeTagsManager {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTasksChangeTagsManager' );
	}

	public function getImageRecommendationApiHandler(): ImageRecommendationApiHandler {
		return $this->coreServices->get( 'GrowthExperimentsImageRecommendationApiHandler' );
	}

}
