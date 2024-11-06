<?php

namespace GrowthExperiments\Maintenance;

use CirrusSearch\CirrusSearchServices;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use MediaWiki\Maintenance\Maintenance;

class UnInvalidateImageSuggestion extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Add an image suggestion to the task list. '
			. 'For development setups only. Updates the search index and clears caches; '
			. 'the user must ensure that the API returns a recommendation for the given page '
			. '(e.g. by using SubpageImageRecommendationProvider).' );
		$this->addOption( 'title', 'Page title', true, true );
		$this->addOption( 'task-type', 'Task type ID (defaults to image-recommendation)', false, true );
		$this->addOption( 'userid', 'User ID (for resetting the user\'s task  cache), defaults to 1', false, true );
	}

	public function execute() {
		$services = $this->getServiceContainer();
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		$cirrusSearchServices = CirrusSearchServices::wrap( $services );

		if ( !$growthExperimentsServices->getGrowthConfig()->get( 'GEDeveloperSetup' ) ) {
			$this->fatalError( 'This script is only for development setups.' );
		}

		$title = $services->getTitleFactory()->newFromText( $this->getOption( 'title' ) );
		if ( !$title || !$title->exists() ) {
			$this->fatalError( 'Invalid title' );
		}
		$taskTypeId = $this->getOption( 'task-type', 'image-recommendation' );
		$taskTypes = $growthExperimentsServices->getNewcomerTasksConfigurationLoader()->getTaskTypes();
		if ( !array_key_exists( $taskTypeId, $taskTypes )
			|| !( $taskTypes[$taskTypeId] instanceof ImageRecommendationBaseTaskType )
		) {
			$this->fatalError( 'Invalid task type ID' );
		}
		$userId = (int)$this->getOption( 'userid', 1 );

		$taskTypeHandler = $growthExperimentsServices->getTaskTypeHandlerRegistry()->get( $taskTypeId );
		// update weighted tag in CirrusSearch index
		$cirrusSearchServices->getWeightedTagsUpdater()->updateWeightedTags(
			$title->toPageIdentity(),
			// @phan-suppress-next-line PhanUndeclaredConstantOfClass
			$taskTypeHandler::WEIGHTED_TAG_PREFIX
		);
		// clear cache used by ImageRecommendationFilter
		$cache = $services->getMainWANObjectCache();
		$cache->delete( ImageRecommendationFilter::makeKey(
			$cache,
			// @phan-suppress-next-line PhanUndeclaredConstantOfClass
			$taskTypeHandler::TASK_TYPE_ID,
			$title->getDBkey()
		) );
		// clear user's CacheDecorator cache
		$cache->delete( $cache->makeKey( 'GrowthExperiments-NewcomerTasks-TaskSet', $userId ) );
	}

}

return UnInvalidateImageSuggestion::class;
