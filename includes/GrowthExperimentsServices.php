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
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\Mentorship\Store\PreferenceMentorStore;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\LinkSubmissionRecorder;
use GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater\SearchIndexUpdater;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\SuggestionsInfo;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
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

	/**
	 * @deprecated since 1.36, use getGrowthConfig or getGrowthWikiConfig instead
	 * @return Config
	 */
	public function getConfig(): Config {
		return $this->coreServices->getConfigFactory()->makeConfig( 'GrowthExperiments' );
	}

	public function getGrowthConfig(): Config {
		return $this->coreServices->get( 'GrowthExperimentsConfig' );
	}

	public function getGrowthWikiConfig(): Config {
		return $this->coreServices->get( 'GrowthExperimentsMultiConfig' );
	}

	public function getLoadBalancer(): ILoadBalancer {
		$databaseCluster = $this->getConfig()->get( 'GEDatabaseCluster' );
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

	public function getLinkRecommendationHelper(): LinkRecommendationHelper {
		return $this->coreServices->get( 'GrowthExperimentsLinkRecommendationHelper' );
	}

	public function getLinkRecommendationProvider(): LinkRecommendationProvider {
		return $this->coreServices->get( 'GrowthExperimentsLinkRecommendationProvider' );
	}

	public function getLinkRecommendationStore(): LinkRecommendationStore {
		return $this->coreServices->get( 'GrowthExperimentsLinkRecommendationStore' );
	}

	public function getLinkSubmissionRecorder(): LinkSubmissionRecorder {
		return $this->coreServices->get( 'GrowthExperimentsLinkSubmissionRecorder' );
	}

	public function getMentorManager(): MentorManager {
		return $this->coreServices->get( 'GrowthExperimentsMentorManager' );
	}

	public function getMentorStore(): MentorStore {
		return $this->coreServices->get( 'GrowthExperimentsMentorStore' );
	}

	public function getDatabaseMentorStore(): DatabaseMentorStore {
		return $this->coreServices->get( 'GrowthExperimentsMentorStoreDatabase' );
	}

	public function getPreferenceMentorStore(): PreferenceMentorStore {
		return $this->coreServices->get( 'GrowthExperimentsMentorStorePreference' );
	}

	public function getNewcomerTasksConfigurationLoader(): ConfigurationLoader {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTasksConfigurationLoader' );
	}

	public function getNewcomerTasksConfigurationValidator(): ConfigurationValidator {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTasksConfigurationValidator' );
	}

	public function getNewcomerTaskTrackerFactory(): TrackerFactory {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTaskTrackerFactory' );
	}

	public function getNewcomerTasksUserOptionsLookup(): NewcomerTasksUserOptionsLookup {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTasksUserOptionsLookup' );
	}

	public function getProtectionFilter(): ProtectionFilter {
		return $this->coreServices->get( 'GrowthExperimentsProtectionFilter' );
	}

	public function getQuestionPosterFactory(): QuestionPosterFactory {
		return $this->coreServices->get( 'GrowthExperimentsQuestionPosterFactory' );
	}

	public function getSearchIndexUpdater(): SearchIndexUpdater {
		return $this->coreServices->get( 'GrowthExperimentsSearchIndexUpdater' );
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

}
