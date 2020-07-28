<?php

namespace GrowthExperiments;

use Config;
use GrowthExperiments\HelpPanel\Tips\TipNodeRenderer;
use GrowthExperiments\HelpPanel\Tips\TipsAssembler;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\Tracker\TrackerFactory;
use MediaWiki\MediaWikiServices;

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

	public function getConfigurationLoader(): ConfigurationLoader {
		return $this->coreServices->get( 'GrowthExperimentsConfigurationLoader' );
	}

	public function getEditInfoService(): EditInfoService {
		return $this->coreServices->get( 'GrowthExperimentsEditInfoService' );
	}

	public function getExperimentUserManager(): ExperimentUserManager {
		return $this->coreServices->get( 'GrowthExperimentsExperimentUserManager' );
	}

	public function getNewcomerTaskTrackerFactory(): TrackerFactory {
		return $this->coreServices->get( 'GrowthExperimentsNewcomerTaskTrackerFactory' );
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
