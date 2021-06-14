<?php

use GrowthExperiments\NewcomerTasks\AddLink\SubpageLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWiki\MediaWikiServices;

# Enable under-development features still behind feature flag:
$wgGENewcomerTasksLinkRecommendationsEnabled = true;
$wgGELinkRecommendationsFrontendEnabled = true;
# Prevent pruning of red links (among other things) for subpage provider.
$wgGEDeveloperSetup = true;

$wgHooks['MediaWikiServices'][] = static function ( MediaWikiServices $services ) {
	$linkRecommendationTaskType = new LinkRecommendationTaskType(
		'link-recommendation', TaskType::DIFFICULTY_EASY, []
	);
	$copyeditTaskType = new TemplateBasedTaskType(
		'link-recommendation', TaskType::DIFFICULTY_MEDIUM, [], []
	);

	# Mock the task suggester to specify what article(s) will be suggested.
	$services->redefineService(
		'GrowthExperimentsTaskSuggesterFactory',
		static function () use ( $linkRecommendationTaskType, $copyeditTaskType ): TaskSuggesterFactory {
			return new StaticTaskSuggesterFactory( [
				new Task( $linkRecommendationTaskType, new TitleValue( NS_MAIN, 'Douglas Adams' ) ),
				new Task( $copyeditTaskType, new TitleValue( NS_MAIN, "The_Hitchhiker's_Guide_to_the_Galaxy" ) )
			] );
		}
	);
};

# Set up SubpageLinkRecommendationProvider, which will take the recommendation from the article's /addlink.json subpage,
# e.g. [[Douglas Adams/addlink.json]]. The output of https://addlink-simple.toolforge.org can be copied there.
$wgHooks['MediaWikiServices'][] = SubpageLinkRecommendationProvider::class . '::onMediaWikiServices';
$wgHooks['ContentHandlerDefaultModelFor'][] =
	SubpageLinkRecommendationProvider::class . '::onContentHandlerDefaultModelFor';
