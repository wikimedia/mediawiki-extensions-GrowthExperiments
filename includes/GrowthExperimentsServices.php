<?php

namespace GrowthExperiments;

use Config;
use GrowthExperiments\HelpPanel\QuestionPoster\QuestionPosterFactory;
use GrowthExperiments\HelpPanel\Tips\TipNodeRenderer;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\AddLink\LinkSubmissionRecorder;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
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

	public function getConfig(): Config {
		return $this->coreServices->getConfigFactory()->makeConfig( 'GrowthExperiments' );
	}

	public function getLoadBalancer(): ILoadBalancer {
		$databaseCluster = $this->getConfig()->get( 'GEDatabaseCluster' );
		if ( $databaseCluster ) {
			return $this->coreServices->getDBLoadBalancerFactory()->getExternalLB( $databaseCluster );
		} else {
			return $this->coreServices->getDBLoadBalancerFactory()->getMainLB();
		}
	}

	public function getConfigurationLoader(): ConfigurationLoader {
		return $this->coreServices->get( 'GrowthExperimentsConfigurationLoader' );
	}

	public function getConfigurationValidator(): ConfigurationValidator {
		return $this->coreServices->get( 'GrowthExperimentsConfigurationValidator' );
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

	public function getNewcomerTaskTrackerFactory(): TrackerFactory {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTaskTrackerFactory' );
	}

	public function getNewcomerTasksUserOptionsLookup(): NewcomerTasksUserOptionsLookup {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTasksUserOptionsLookup' );
	}

	public function getProtectionFilter(): ProtectionFilter {
		return $this->coreServices->get( 'GrowthExperimentsProtectionFilter' );
	}

	public function getTaskTypeHandlerRegistry(): TaskTypeHandlerRegistry {
		return $this->coreServices->get( 'GrowthExperimentsTaskTypeHandlerRegistry' );
	}

	public function getQuestionPosterFactory(): QuestionPosterFactory {
		return $this->coreServices->get( 'GrowthExperimentsQuestionPosterFactory' );
	}

	public function getTaskSuggesterFactory(): TaskSuggesterFactory {
		return $this->coreServices->get( 'GrowthExperimentsTaskSuggesterFactory' );
	}

	public function getTipsAssembler(): TipsAssembler {
		return $this->coreServices->get( 'GrowthExperimentsTipsAssembler' );
	}

	public function getTipNodeRenderer(): TipNodeRenderer {
		return $this->coreServices->get( 'GrowthExperimentsTipNodeRenderer' );
	}

}
