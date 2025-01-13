<?php

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\QualityGateDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleValue;

$wgGEUseCommunityConfigurationExtension = true;
$wgGENewcomerTasksLinkRecommendationsEnabled = true;
$wgGELinkRecommendationsFrontendEnabled = true;
$wgGESurfacingStructuredTasksEnabled = true;

$wgHooks['MediaWikiServices'][] = static function ( MediaWikiServices $services ) {
	$copyEditTaskType = new TemplateBasedTaskType(
		'copyedit',
		GrowthExperiments\NewcomerTasks\TaskType\TaskType::DIFFICULTY_EASY,
		[],
		[ new TitleValue( NS_MAIN, 'Awkward' ) ]
	);

	# Mock the task suggester to specify what article(s) will be suggested.
	$services->redefineService(
		'GrowthExperimentsTaskSuggesterFactory',
		static function () use (
			$copyEditTaskType,
			$services
		): TaskSuggesterFactory {
			$staticSuggesterFactory = new StaticTaskSuggesterFactory( [
				new Task( $copyEditTaskType, new TitleValue( NS_MAIN, 'Classical kemençe' ) ),
				new Task( $copyEditTaskType, new TitleValue( NS_MAIN, 'Cretan lyra' ) )
			], $services->getTitleFactory() );

			$growthServices = GrowthExperimentsServices::wrap( $services );
			$taskSuggesterFactory = new DecoratingTaskSuggesterFactory(
				$staticSuggesterFactory,
				$services->getObjectFactory(),
				[
					[
						'class' => QualityGateDecorator::class,
						'args' => [
							$growthServices->getNewcomerTasksConfigurationLoader(),
							$growthServices->getImageRecommendationSubmissionLogFactory(),
							$growthServices->getSectionImageRecommendationSubmissionLogFactory(),
							$growthServices->getLinkRecommendationSubmissionLogFactory(),
							$growthServices->getGrowthExperimentsCampaignConfig()
						]
					],
				]
			);

			return $taskSuggesterFactory;
		}
	);
};

// Activate suggested edits for new users, complete various tours.
$wgHooks['UserGetDefaultOptions'][] = static function ( &$defaultOptions ) {
	$defaultOptions[SuggestedEdits::ACTIVATED_PREF] = true;
};
