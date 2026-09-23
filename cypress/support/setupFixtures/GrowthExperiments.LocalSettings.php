<?php

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\AddImage\SubpageImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ReviseTone\SubpageReviseToneRecommendationProvider;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\DecoratingTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\QualityGateDecorator;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ReviseToneTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleValue;

$wgGENewcomerTasksLinkRecommendationsEnabled = true;
$wgGELinkRecommendationsFrontendEnabled = true;

// FIXME: Used on patch-demo
$wgGEReviseToneSuggestedEditEnabled = true;
$wgGEReviseToneRecommendationProvider = 'subpage';

/** EDIT CHECK START */
$wgVisualEditorEditCheck = true;
/** EDIT CHECK END */

$wgMaxArticleSize = 100;
$wgParsoidSettings['wt2htmlLimits']['wikitextSize'] = 100 * 1024;
$wgParsoidSettings['html2wtLimits']['htmlSize'] = 500 * 1024;
$wgGEDeveloperSetup = true;

// region AccountSetup
$wgGEAccountSetupExperimentStartRegistrationDate = '1970-01-01T00:00:00';
$wgTestKitchenEnableExperiments = true;
// endregion

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

	$reviseToneTaskType = new ReviseToneTaskType(
		'revise-tone',
		GrowthExperiments\NewcomerTasks\TaskType\TaskType::DIFFICULTY_EASY,
	);

	# Mock the task suggester to specify what article(s) will be suggested.
	$services->redefineService(
		'GrowthExperimentsTaskSuggesterFactory',
		static function () use (
			$copyEditTaskType,
			$imageRecommendationTaskType,
			$linkRecommendationTaskType,
			$reviseToneTaskType,
			$services
		): TaskSuggesterFactory {
			$growthServices = GrowthExperimentsServices::wrap( $services );
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
				new Task( $reviseToneTaskType, new TitleValue( NS_MAIN, "Kristallsee" ) ),
				new Task( $reviseToneTaskType, new TitleValue( NS_MAIN, "Eldfjall" ) ),
				new Task( $imageRecommendationTaskType, new TitleValue( NS_MAIN, "Ma'amoul" ) ),
				/*
				 * Appended, so the tasks above keep the positions the browser tests
				 * assert. A patchdemo wiki reaches only revise-tone tasks: link
				 * recommendations need database rows nothing on the wiki can write,
				 * and copyedit is rewritten to revise-tone before the request leaves
				 * the client.
				 * Three is also the dashboard feed's summary size, so a fourth is
				 * what makes it offer the full list at all.
				 */
				new Task( $reviseToneTaskType, new TitleValue( NS_MAIN, '4-8-2' ) ),
				new Task( $reviseToneTaskType, new TitleValue( NS_MAIN, 'Classical kemençe' ) ),
				new Task( $reviseToneTaskType, new TitleValue( NS_MAIN, 'Cretan lyra' ) ),
			], $services->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() ),
				$growthServices->getLogger() );

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
				],
				$growthServices->getLogger()
			);

			return $taskSuggesterFactory;
		}
	);
};

$wgGEImageRecommendationApiHandler = 'mvp';
// Set up SubpageImageRecommendationProvider, which will take the suggestion from the article's /addimage.json subpage
$wgHooks['MediaWikiServices'][] = [ SubpageImageRecommendationProvider::class, 'onMediaWikiServices' ];
$wgHooks['ContentHandlerDefaultModelFor'][] =
	[ SubpageImageRecommendationProvider::class, 'onContentHandlerDefaultModelFor' ];
$wgHooks['ContentHandlerDefaultModelFor'][] =
	[ SubpageReviseToneRecommendationProvider::class, 'onContentHandlerDefaultModelFor' ];
// Use Commons as a foreign file repository.
$wgUseInstantCommons = true;

/*
 * Let the task cards show a description without a Wikibase repo behind them. The local
 * source of prop=description reads the SHORTDESC parser function, which Wikibase only
 * hooks up when this is on, so leaving it off keeps the description slot empty on a
 * client-only wiki. Harmless where Wikibase is absent: nothing reads the setting.
 */
$wgWBClientSettings['allowLocalShortDesc'] = true;

/*
 * Set up service URL for links.
 * It is not actually used, but GrowthExperimentsLinkRecommendationProviderUncached does check for it.
 */
$wgGELinkRecommendationServiceUrl = 'https://example.com/service/linkrecommendation';

// Activate suggested edits for new users, complete various tours.
$wgHooks['UserGetDefaultOptions'][] = static function ( &$defaultOptions ) {
	$defaultOptions[SuggestedEdits::ACTIVATED_PREF] = true;
};

// as in Wikimedia production (related to T415659)
$wgHiddenPrefs[] = 'realname';
