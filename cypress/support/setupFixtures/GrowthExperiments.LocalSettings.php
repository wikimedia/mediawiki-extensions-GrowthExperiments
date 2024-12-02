<?php

use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use MediaWiki\MediaWikiServices;

$wgGEUseCommunityConfigurationExtension = true;
$wgGENewcomerTasksLinkRecommendationsEnabled = true;
$wgGELinkRecommendationsFrontendEnabled = true;
$wgGESurfacingStructuredTasksEnabled = true;

$wgHooks['MediaWikiServices'][] = static function ( MediaWikiServices $services ) {
	# Mock the task suggester to specify what article(s) will be suggested.
	$services->redefineService(
		'GrowthExperimentsTaskSuggesterFactory',
		static function () use ( $services ): TaskSuggesterFactory {
			return new StaticTaskSuggesterFactory( [], $services->getTitleFactory() );
		}
	);
};
