<?php

namespace GrowthExperiments\Tests\Integration;

use CirrusSearch\WeightedTagsUpdater;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Request\FauxRequest;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use RecentChange;
use StatusValue;
use stdClass;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\TestingAccessWrapper;
use WikiPage;

/**
 * @covers \GrowthExperiments\HomepageHooks
 * @group Database
 */
class HomepageHooksTest extends MediaWikiIntegrationTestCase {

	public function testGetTaskTypesJson() {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->onlyMethods( [ 'loadTaskTypes' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )
			->willReturn( [
				new TaskType( 'tt1', TaskType::DIFFICULTY_EASY ),
				new TaskType( 'tt2', TaskType::DIFFICULTY_EASY ),
			] );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$context = new RL\Context( $this->getServiceContainer()->getResourceLoader(),
			RequestContext::getMain()->getRequest() );
		$configData = HomepageHooks::getTaskTypesJson( $context );
		$this->assertSame( [ 'tt1', 'tt2' ], array_keys( $configData ) );
	}

	public function testGetTaskTypesJson_error() {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->onlyMethods( [ 'loadTaskTypes' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )
			->willReturn( StatusValue::newFatal( 'foo' ) );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$context = new RL\Context( $this->getServiceContainer()->getResourceLoader(),
			RequestContext::getMain()->getRequest() );
		$configData = HomepageHooks::getTaskTypesJson( $context );
		$this->assertSame( [ '_error' => '(foo)' ], $configData );
	}

	public function testGetAQSConfigJson() {
		$config = HomepageHooks::getAQSConfigJson();
		$this->assertInstanceOf( stdClass::class, $config );
		$this->assertObjectHasProperty( 'project', $config );
	}

	/**
	 * @dataProvider provideOnSearchDataForIndex
	 */
	public function testOnSearchDataForIndex(
		int $pageRevId,
		?int $linkRecommendationRevId,
		?int $linkRecommendationPrimaryRevId,
		bool $expectPrimaryRead,
		bool $expectDeleted
	) {
		$homepageHooks = $this->getHomepageHooks();
		$fields = [];
		$page = $this->createNoOpMock( WikiPage::class, [ 'getRevisionRecord', 'getTitle' ] );
		TestingAccessWrapper::newFromObject( $homepageHooks )->canAccessPrimary = true;
		TestingAccessWrapper::newFromObject( $homepageHooks )->config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GETempLinkRecommendationSwitchTagClearHook' => false,
		] );
		$linkRecommendationStore = $this->createNoOpMock( LinkRecommendationStore::class,
			[ 'getByLinkTarget', 'getRevisionId' ] );
		TestingAccessWrapper::newFromObject( $homepageHooks )->linkRecommendationStore = $linkRecommendationStore;
		$linkRecommendationHelper = $this->createNoOpMock( LinkRecommendationHelper::class,
			[ 'deleteLinkRecommendation' ] );
		TestingAccessWrapper::newFromObject( $homepageHooks )->linkRecommendationHelper = $linkRecommendationHelper;
		$page->method( 'getTitle' )->willReturn(
			$this->createConfiguredMock( Title::class, [
				'toPageIdentity' => $this->createNoOpMock( ProperPageIdentity::class ),
			] )
		);

		$page->method( 'getRevisionRecord' )->willReturn(
			$this->createConfiguredMock( RevisionRecord::class, [ 'getId' => $pageRevId ] )
		);
		if ( $linkRecommendationRevId ) {
			$linkRecommendation = $this->createNoOpMock( LinkRecommendation::class, [ 'getRevisionId' ] );
			$linkRecommendation->method( 'getRevisionId' )->willReturn( $linkRecommendationRevId );
		} else {
			$linkRecommendation = null;
		}
		if ( $linkRecommendationPrimaryRevId ) {
			$linkRecommendationPrimary = $this->createNoOpMock( LinkRecommendation::class, [ 'getRevisionId' ] );
			$linkRecommendationPrimary->method( 'getRevisionId' )->willReturn( $linkRecommendationPrimaryRevId );
		} else {
			$linkRecommendationPrimary = null;
		}
		$linkRecommendationStore->expects( $expectPrimaryRead ? $this->exactly( 2 ) : $this->once() )
			->method( 'getByLinkTarget' )->willReturnCallback(
				static function (
					LinkTarget $title, int $flags
				) use ( $linkRecommendation, $linkRecommendationPrimary ) {
					return $flags ? $linkRecommendationPrimary : $linkRecommendation;
				}
			);

		$homepageHooks->doSearchDataForIndex(
			$fields,
			$page,
			$page->getRevisionRecord()
		);
		if ( $expectDeleted ) {
			$this->assertArrayHasKey( 'weighted_tags', $fields );
			$this->assertSame( [ 'recommendation.link/__DELETE_GROUPING__' ], $fields['weighted_tags'] );
		} else {
			$this->assertArrayNotHasKey( 'weighted_tags', $fields );
		}
	}

	public static function provideOnSearchDataForIndex() {
		return [
			// page revid, recommendation revid on replica read, on primary read, expect primary read, expect delete
			'page has no recommendation' => [ 100, null, null, false, false ],
			'page has current recommendation (purge)' => [ 100, 100, null, false, false ],
			'page has old recommendation (edit)' => [ 100, 90, 90, true, true ],
			'page has current recommendation + replag (purge after edit + generate)' => [ 100, 90, 100, true, false ],
			'recommendation was deleted recently (purge after edit)' => [ 100, 90, null, true, false ],
		];
	}

	public function testNoOnSearchDataForIndexIfSwitchFlagIsTrue(): void {
		$homepageHooks = $this->getHomepageHooks();
		TestingAccessWrapper::newFromObject( $homepageHooks )->config = new HashConfig( [
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GETempLinkRecommendationSwitchTagClearHook' => true,
		] );
		$linkRecommendationStore = $this->createNoOpMock( LinkRecommendationStore::class );
		$linkRecommendationStore->expects( $this->never() )->method( 'getByLinkTarget' );
		TestingAccessWrapper::newFromObject( $homepageHooks )->linkRecommendationStore = $linkRecommendationStore;
		$fields = [];

		$homepageHooks->doSearchDataForIndex(
			$fields,
			$this->createNoOpMock( WikiPage::class ),
			$this->createNoOpMock( RevisionRecord::class ),
		);
	}

	public function testClearLinkRecommendationOnPageSaveComplete(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$wikiPage = $this->getExistingTestPage();
		$expectedPageIdentity = $wikiPage->getTitle()->toPageIdentity();
		$weightedTagsUpdaterMock = $this->createMock( WeightedTagsUpdater::class );
		$weightedTagsUpdaterMock->expects( $this->once() )
			->method( 'resetWeightedTags' )
			->with( $expectedPageIdentity, [ LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX ] );
		$this->setService( WeightedTagsUpdater::SERVICE, $weightedTagsUpdaterMock );
		$this->overrideConfigValues( [
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GETempLinkRecommendationSwitchTagClearHook' => true,
		] );
		$linkRecommendation = new LinkRecommendation(
			$wikiPage->getTitle(),
			$wikiPage->getId(),
			0,
			[],
			LinkRecommendation::getMetadataFromArray( [] )
		);
		$linkRecommendationStore = $this->getServiceContainer()->get( 'GrowthExperimentsLinkRecommendationStore' );
		$linkRecommendationStore->insert( $linkRecommendation );

		$this->editPage( $wikiPage, 'new content' );

		$fromPageId = 0;
		$this->assertCount( 0, $linkRecommendationStore->getAllRecommendations( 100, $fromPageId ) );
	}

	public function testClearLinkRecommendationNoPrimaryWriteWithoutReplicaMatch(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$wikiPage = $this->getExistingTestPage();
		$expectedPageIdentity = $wikiPage->getTitle()->toPageIdentity();
		$weightedTagsUpdaterMock = $this->createMock( WeightedTagsUpdater::class );
		$weightedTagsUpdaterMock->expects( $this->once() )
			->method( 'resetWeightedTags' )
			->with( $expectedPageIdentity, [ LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX ] );
		$this->setService( WeightedTagsUpdater::SERVICE, $weightedTagsUpdaterMock );
		$this->overrideConfigValues( [
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GETempLinkRecommendationSwitchTagClearHook' => true,
		] );
		$mockLinkRecommendationStore = $this->createMock( LinkRecommendationStore::class );
		$mockLinkRecommendationStore->expects( $this->once() )
			->method( 'getByPageId' )
			->with( $wikiPage->getId(), IDBAccessObject::READ_NORMAL )
			->willReturn( null );
		$mockLinkRecommendationStore->expects( $this->never() )
			->method( 'deleteByPageIds' );
		$this->setService( 'GrowthExperimentsLinkRecommendationStore', $mockLinkRecommendationStore );

		$this->editPage( $wikiPage, 'new content' );
	}

	/**
	 * @return HomepageHooks
	 */
	private function getHomepageHooks(): HomepageHooks {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		return new HomepageHooks(
			$services->getMainConfig(),
			$services->getDBLoadBalancer(),
			$services->getUserOptionsManager(),
			$services->getUserOptionsLookup(),
			$services->getUserIdentityUtils(),
			$services->getNamespaceInfo(),
			$services->getTitleFactory(),
			$services->getStatsFactory(),
			$services->getJobQueueGroup(),
			$growthServices->getNewcomerTasksConfigurationLoader(),
			$growthServices->getGrowthExperimentsCampaignConfig(),
			$growthServices->getExperimentUserManager(),
			$growthServices->getTaskTypeHandlerRegistry(),
			$growthServices->getTaskSuggesterFactory(),
			$growthServices->getNewcomerTasksUserOptionsLookup(),
			$growthServices->getLinkRecommendationStore(),
			$growthServices->getLinkRecommendationHelper(),
			$services->getSpecialPageFactory(),
			$growthServices->getNewcomerTasksChangeTagsManager(),
			$growthServices->getSuggestionsInfo(),
			$growthServices->getUserImpactLookup(),
			$growthServices->getUserImpactStore()
		);
	}

	public function testOnRecentChange_save() {
		// FIXME: These tests should cover a success case as well, and should
		// use a data provider for the test cases.
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', true );
		$homepageHooks = $this->getHomepageHooks();
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		TestingAccessWrapper::newFromObject( $homepageHooks )
			->userOptionsLookup = $services->getUserOptionsLookup();
		TestingAccessWrapper::newFromObject( $homepageHooks )
			->taskTypeHandlerRegistry = $growthServices->getTaskTypeHandlerRegistry();

		$request = new FauxRequest( [ 'taskType' => 'copyedit' ], true );
		$this->setRequest( $request );
		// 1. Anon users don't get to add newcomer task tags.
		$recentChange = new RecentChange();
		$recentChange->setAttribs( [
			'tags' => [ 'foo' ],
			'rc_user' => 0,
			'rc_user_text' => 'Anonymous'
		] );
		$homepageHooks->onRecentChange_save( $recentChange );
		$this->assertArrayEquals( [ 'foo' ], $recentChange->getAttribute( 'tags' ) );

		// Auth user tests with users who have activated Suggested Edits.
		$user = $this->getMutableTestUser()->getUser();
		$services->getUserOptionsManager()->setOption( $user, SuggestedEdits::ACTIVATED_PREF, 1 );
		$services->getUserOptionsManager()->saveOptions( $user );

		// 2. No tags added without plugins.
		$recentChange = new RecentChange();
		$recentChange->setAttribs( [
			'tags' => [ 'foo' ],
			'rc_user' => $user->getId()
		] );
		$homepageHooks->onRecentChange_save( $recentChange );
		$this->assertArrayEquals( [ 'foo' ], $recentChange->getAttribute( 'tags' ) );
	}

}
