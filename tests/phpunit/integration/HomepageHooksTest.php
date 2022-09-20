<?php

namespace GrowthExperiments\Tests;

use FauxRequest;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use HashConfig;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\Revision\RevisionRecord;
use MediaWikiIntegrationTestCase;
use RawMessage;
use RecentChange;
use RequestContext;
use StatusValue;
use stdClass;
use Title;
use Wikimedia\TestingAccessWrapper;
use WikiPage;

/**
 * @coversDefaultClass \GrowthExperiments\HomepageHooks
 */
class HomepageHooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::getTaskTypesJson
	 */
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
		$context = new RL\Context( MediaWikiServices::getInstance()->getResourceLoader(),
			RequestContext::getMain()->getRequest() );
		$configData = HomepageHooks::getTaskTypesJson( $context );
		$this->assertSame( [ 'tt1', 'tt2' ], array_keys( $configData ) );
	}

	/**
	 * @covers ::getTaskTypesJson
	 */
	public function testGetTaskTypesJson_error() {
		$configurationLoader = $this->getMockBuilder( ConfigurationLoader::class )
			->onlyMethods( [ 'loadTaskTypes' ] )
			->getMockForAbstractClass();
		$configurationLoader->method( 'loadTaskTypes' )
			->willReturn( StatusValue::newFatal( new RawMessage( 'foo' ) ) );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );
		$context = new RL\Context( MediaWikiServices::getInstance()->getResourceLoader(),
			RequestContext::getMain()->getRequest() );
		$configData = HomepageHooks::getTaskTypesJson( $context );
		$this->assertSame( [ '_error' => 'foo' ], $configData );
	}

	/**
	 * @covers ::getAQSConfigJson
	 */
	public function testGetAQSConfigJson() {
		$config = HomepageHooks::getAQSConfigJson();
		$this->assertInstanceOf( stdClass::class, $config );
		$this->assertObjectHasAttribute( 'project', $config );
	}

	/**
	 * @dataProvider provideOnSearchDataForIndex
	 * @covers ::onSearchDataForIndex
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

	public function provideOnSearchDataForIndex() {
		return [
			// page revid, recommendation revid on replica read, on primary read, expect primary read, expect delete
			'page has no recommendation' => [ 100, null, null, false, false ],
			'page has current recommendation (purge)' => [ 100, 100, null, false, false ],
			'page has old recommendation (edit)' => [ 100, 90, 90, true, true ],
			'page has current recommendation + replag (purge after edit + generate)' => [ 100, 90, 100, true, false ],
			'recommendation was deleted recently (purge after edit)' => [ 100, 90, null, true, false ],
		];
	}

	/**
	 * @return HomepageHooks
	 */
	private function getHomepageHooks(): HomepageHooks {
		// hack - phpunit refuses to proxy calls if the constructor is disabled, and the constructor
		// has way too many parameters
		return new class extends HomepageHooks {
			public function __construct() {
			}
		};
	}

	/**
	 * @covers ::onRecentChange_save
	 */
	public function testOnRecentChange_save() {
		// FIXME: These tests should cover a success case as well, and should
		// use a data provider for the test cases.
		$this->setMwGlobals( [ 'GEHomepageSuggestedEditsEnabled' => true ] );
		$homepageHooks = $this->getHomepageHooks();
		$services = MediaWikiServices::getInstance();
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
