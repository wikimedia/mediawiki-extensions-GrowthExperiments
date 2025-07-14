<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddImage\ServiceImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use MediaWiki\Json\FormatJson;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Status\Status;
use StatusValue;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Script to allow obtaining image suggestion data. Useful for verifying what
 * ServiceImageRecommendationProvider is doing.
 */
class GetImageSuggestionData extends Maintenance {

	/** @inheritDoc */
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Get image suggestion data via ServiceImageRecommendationProvider' );
		$this->addOption(
			'title',
			'The page title to use in fetching image suggestion data.',
			true
		);
		$this->addOption(
			'section-level',
			'Get section-level suggestions instead of top-level suggestions.'
		);
		$this->addOption(
			'max-suggestions',
			'The maximum number of valid image suggestions to process per title.',
			false,
			true
		);
	}

	/** @inheritDoc */
	public function execute() {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$serviceImageRecommendationProvider = $growthServices->getImageRecommendationProviderUncached();
		if ( !$serviceImageRecommendationProvider instanceof ServiceImageRecommendationProvider ) {
			// This isn't really necessary, but done to make phan happy.
			$this->fatalError(
				get_class( $serviceImageRecommendationProvider ) . ' is not an instance of ' .
				ServiceImageRecommendationProvider::class
			);
		}
		// Not needed in production, but simplifies local environment/production usage.
		global $wgGEImageRecommendationServiceUseTitles;
		$wgGEImageRecommendationServiceUseTitles = true;
		$title = $services->getTitleFactory()->newFromText( $this->getOption( 'title' ) );
		if ( !$title instanceof LinkTarget ) {
			$this->fatalError( 'Unable to make a LinkTarget from ' . $this->getOption( 'title' ) );
		}
		$maxSuggestions = (int)$this->getOption( 'max-suggestions', 1 );
		if ( $maxSuggestions < 1 ) {
			$this->fatalError( 'max-suggestions needs to be > 1' );
		}
		$serviceImageRecommendationProvider->setMaxSuggestionsToProcess( $maxSuggestions );
		if ( $this->hasOption( 'section-level' ) ) {
			$taskType = $growthServices->getNewcomerTasksConfigurationLoader()
				->getTaskTypes()[ SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID ];
		} else {
			$taskType = $growthServices->getNewcomerTasksConfigurationLoader()
				->getTaskTypes()[ ImageRecommendationTaskTypeHandler::TASK_TYPE_ID ];
		}
		$result = $serviceImageRecommendationProvider->get( $title, $taskType );
		if ( $result instanceof StatusValue ) {
			$this->fatalError( Status::wrap( $result )->getWikiText( false, false, 'en' ) );
		} else {
			$jsonData = FormatJson::encode( $result, true );
			$this->output( $jsonData . PHP_EOL );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = GetImageSuggestionData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
