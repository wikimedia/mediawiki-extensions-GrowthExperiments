<?php

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\AddImage\SubpageImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\QualityGateDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleValue;

$wgGENewcomerTasksLinkRecommendationsEnabled = true;
$wgGELinkRecommendationsFrontendEnabled = true;

$wgMaxArticleSize = 100;
$wgParsoidSettings['wt2htmlLimits']['wikitextSize'] = 100 * 1024;
$wgParsoidSettings['html2wtLimits']['htmlSize'] = 500 * 1024;
$wgGEDeveloperSetup = true;

$wgHooks['MediaWikiServices'][] = static function ( MediaWikiServices $services ) {
	$copyEditTaskType = new TemplateBasedTaskType(
		'copyedit',
		GrowthExperiments\NewcomerTasks\TaskType\TaskType::DIFFICULTY_EASY,
		[],
		[ new TitleValue( NS_MAIN, 'Awkward' ) ]
	);
	$imageRecommendationTaskType = new ImageRecommendationTaskType(
		'image-recommendation', GrowthExperiments\NewcomerTasks\TaskType\TaskType::DIFFICULTY_MEDIUM, []
	);
	$linkRecommendationTaskType = new LinkRecommendationTaskType(
		'link-recommendation', GrowthExperiments\NewcomerTasks\TaskType\TaskType::DIFFICULTY_EASY, []
	);

	# Mock the task suggester to specify what article(s) will be suggested.
	$services->redefineService(
		'GrowthExperimentsTaskSuggesterFactory',
		static function () use (
			$copyEditTaskType,
			$imageRecommendationTaskType,
			$linkRecommendationTaskType,
			$services
		): TaskSuggesterFactory {
			$staticSuggesterFactory = new StaticTaskSuggesterFactory( [
				new Task( $linkRecommendationTaskType, new TitleValue( NS_MAIN, 'Douglas Adams' ) ),
				new Task(
					$linkRecommendationTaskType, new TitleValue( NS_MAIN, "The_Hitchhiker's_Guide_to_the_Galaxy" )
				),
				new Task(
					$linkRecommendationTaskType, new TitleValue( NS_MAIN, "JR-430 Mountaineer" )
				),
				new Task( $copyEditTaskType, new TitleValue( NS_MAIN, 'Classical kemençe' ) ),
				new Task( $copyEditTaskType, new TitleValue( NS_MAIN, 'Cretan lyra' ) ),
				new Task( $imageRecommendationTaskType, new TitleValue( NS_MAIN, "Ma'amoul" ) ),
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

$wgGEImageRecommendationApiHandler = 'mvp';
// Set up SubpageImageRecommendationProvider, which will take the suggestion from the article's /addimage.json subpage
$wgHooks['MediaWikiServices'][] = SubpageImageRecommendationProvider::class . '::onMediaWikiServices';
$wgHooks['ContentHandlerDefaultModelFor'][] =
	SubpageImageRecommendationProvider::class . '::onContentHandlerDefaultModelFor';
// Use Commons as a foreign file repository.
$wgUseInstantCommons = true;

/*
 * Set up service URL for links.
 * It is not actually used, but GrowthExperimentsLinkRecommendationProviderUncached does check for it.
 */
$wgGELinkRecommendationServiceUrl = 'https://example.com/service/linkrecommendation';

// Activate suggested edits for new users, complete various tours.
$wgHooks['UserGetDefaultOptions'][] = static function ( &$defaultOptions ) {
	$defaultOptions[SuggestedEdits::ACTIVATED_PREF] = true;
};
