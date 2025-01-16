<?php

use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\AddImage\SubpageImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddLink\SubpageLinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\TourHooks;
use GrowthExperiments\UserImpact\EditingStreak;
use GrowthExperiments\UserImpact\StaticUserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactLookup;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;

// Raise limits from I2aead24cb7f47
if ( defined( 'MW_QUIBBLE_CI' ) ) {
	$wgMaxArticleSize = 100;
	$wgParsoidSettings['wt2htmlLimits']['wikitextSize'] = 100 * 1024;
	$wgParsoidSettings['html2wtLimits']['htmlSize'] = 500 * 1024;
}

# Enable under-development features still behind feature flag:
$wgGENewcomerTasksLinkRecommendationsEnabled = true;
$wgGELinkRecommendationsFrontendEnabled = true;
# Prevent pruning of red links (among other things) for subpage provider.
$wgGEDeveloperSetup = true;

// Set $wgPageViewInfoWikimediaDomain for page view info URL construction.
$wgPageViewInfoWikimediaDomain = 'en.wikipedia.org';

$wgHooks['MediaWikiServices'][] = static function ( MediaWikiServices $services ) {
	$imageRecommendationTaskType = new ImageRecommendationTaskType(
		'image-recommendation', GrowthExperiments\NewcomerTasks\TaskType\TaskType::DIFFICULTY_MEDIUM, []
	);
	$linkRecommendationTaskType = new LinkRecommendationTaskType(
		'link-recommendation', TaskType::DIFFICULTY_EASY, []
	);

	# Mock the task suggester to specify what article(s) will be suggested.
	$services->redefineService(
		'GrowthExperimentsTaskSuggesterFactory',
		static function () use (
			$imageRecommendationTaskType, $linkRecommendationTaskType, $services
		): TaskSuggesterFactory {
			return new StaticTaskSuggesterFactory( [
				new Task( $imageRecommendationTaskType, new TitleValue( NS_MAIN, "Ma'amoul" ) ),
				new Task( $imageRecommendationTaskType, new TitleValue( NS_MAIN, "1886_in_Chile" ) ),
				new Task( $linkRecommendationTaskType, new TitleValue( NS_MAIN, 'Douglas Adams' ) ),
				new Task(
					$linkRecommendationTaskType, new TitleValue( NS_MAIN, "The_Hitchhiker's_Guide_to_the_Galaxy" )
				),
			], $services->getTitleFactory() );
		}
	);

	// Set up a fake user impact lookup service for CI.
	if ( defined( 'MW_QUIBBLE_CI' ) ) {
		$staticUserImpactLookup = new StaticUserImpactLookup( [
			1 => new GrowthExperiments\UserImpact\ExpensiveUserImpact(
				new UserIdentityValue( 1, 'Admin' ),
				10,
				[ 0 => 2 ],
				[
					'2022-08-24' => 1,
					'2022-08-25' => 1
				],
				[ 'copyedit' => 1, 'link-recommendation' => 1 ],
				1,
				2,
				wfTimestamp( TS_UNIX, '20220825000000' ),
				[
					'2022-08-24' => 1000,
					'2022-08-25' => 2000
				],
				[
					'Foo' => [
						'firstEditDate' => '2022-08-24',
						'newestEdit' => '20220825143817',
						'viewsCount' => 1000,
						'views' => [
							'2022-08-24' => 500,
							'2022-08-25' => 500
						]
					],
					'Bar' => [
						'firstEditDate' => '2022-08-24',
						'newestEdit' => '20220825143818',
						'viewsCount' => 2000,
						'views' => [
							'2022-08-24' => 1000,
							'2022-08-25' => 1000
						]
					]
				],
				new EditingStreak(),
				2
			)
		] );
		$services->redefineService( 'GrowthExperimentsUserImpactLookup',
			static function () use ( $staticUserImpactLookup ): UserImpactLookup {
				return $staticUserImpactLookup;
			} );
		$services->redefineService( 'GrowthExperimentsUserImpactStore',
			static function () use ( $staticUserImpactLookup ): UserImpactLookup {
				return $staticUserImpactLookup;
			} );
	}
};

# Set up SubpageLinkRecommendationProvider, which will take the recommendation from the article's /addlink.json subpage,
# e.g. [[Douglas Adams/addlink.json]]. The output of https://addlink-simple.toolforge.org can be copied there.
$wgHooks['MediaWikiServices'][] = SubpageLinkRecommendationProvider::class . '::onMediaWikiServices';
$wgHooks['ContentHandlerDefaultModelFor'][] =
	SubpageLinkRecommendationProvider::class . '::onContentHandlerDefaultModelFor';
# Same for image recommendations, with addimage.json and http://image-suggestion-api.wmcloud.org/?doc
$wgHooks['MediaWikiServices'][] = SubpageImageRecommendationProvider::class . '::onMediaWikiServices';
$wgHooks['ContentHandlerDefaultModelFor'][] =
	SubpageImageRecommendationProvider::class . '::onContentHandlerDefaultModelFor';
// Use Commons as a foreign file repository.
$wgUseInstantCommons = true;
// Set up service URL for links.
$wgGELinkRecommendationServiceUrl = 'https://api.wikimedia.org/service/linkrecommendation';

// Conditionally load Parsoid in CI
if ( defined( 'MW_QUIBBLE_CI' ) && !is_dir( "$IP/services/parsoid" ) ) {
	$PARSOID_INSTALL_DIR = "$IP/vendor/wikimedia/parsoid";
	wfLoadExtension( 'Parsoid', "$PARSOID_INSTALL_DIR/extension.json" );
}

// Activate suggested edits for new users, complete various tours.
$wgHooks['UserGetDefaultOptions'][] = static function ( &$defaultOptions ) {
	$defaultOptions[SuggestedEdits::ACTIVATED_PREF] = true;
	$defaultOptions[TourHooks::TOUR_COMPLETED_HELP_PANEL] = true;
	$defaultOptions[TourHooks::TOUR_COMPLETED_HOMEPAGE_DISCOVERY] = true;
	$defaultOptions[TourHooks::TOUR_COMPLETED_HOMEPAGE_MENTORSHIP] = true;
	$defaultOptions[TourHooks::TOUR_COMPLETED_HOMEPAGE_WELCOME] = true;
};

// TODO: Migrate browser tests to CommunityConfiguration 2.0 and remove this line (T380581)
$wgGEUseCommunityConfigurationExtension = false;
