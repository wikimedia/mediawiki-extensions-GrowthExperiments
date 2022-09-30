<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddImage\ServiceImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use Maintenance;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use StatusValue;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

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
			'max-suggestions',
			'The maximum number of valid image suggestions to process per title.',
			false,
			true
		);
	}

	/** @inheritDoc */
	public function execute() {
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( MediaWikiServices::getInstance() );
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
		$taskType = $growthServices->getNewcomerTasksConfigurationLoader()
			->getTaskTypes()[ImageRecommendationTaskTypeHandler::TASK_TYPE_ID];
		$result = $serviceImageRecommendationProvider->get( $title, $taskType );
		$data = $result instanceof StatusValue ? $result->getErrors() : $result;
		$jsonData = FormatJson::encode( $data, true );
		if ( $result instanceof StatusValue ) {
			$this->fatalError( $jsonData );
		} else {
			$this->output( $jsonData . PHP_EOL );
		}
	}
}

$maintClass = GetImageSuggestionData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
