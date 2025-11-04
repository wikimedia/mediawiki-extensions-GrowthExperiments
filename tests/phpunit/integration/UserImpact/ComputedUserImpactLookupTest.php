<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\UserImpact\ComputedUserImpactLookup;
use MediaWiki\Extension\PageViewInfo\PageViewService;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;
use StatusValue;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \GrowthExperiments\UserImpact\ComputedUserImpactLookup
 */
class ComputedUserImpactLookupTest extends ApiTestCase {

	public function testGetUserImpact_empty() {
		$this->overrideConfigValue( 'GEReviseToneSuggestedEditEnabled', true );
		// This is a lazy way of ensuring that the tag exists. Revision 1 is the main page,
		// created by the installer.
		$this->getServiceContainer()->getChangeTagsStore()->addTags(
			TemplateBasedTaskTypeHandler::NEWCOMER_TASK_COPYEDIT_TAG, null, 1
		);

		$userIdentity = $this->getMutableTestUser()->getUserIdentity();
		$userImpactLookup = $this->getServiceContainer()->get( 'GrowthExperimentsUserImpactLookup_Computed' );
		$userImpact = $userImpactLookup->getUserImpact( $userIdentity );

		$this->assertTrue( $userIdentity->equals( $userImpact->getUser() ) );
		$this->assertSame( [], $userImpact->getEditCountByNamespace() );
		$this->assertSame( [], $userImpact->getEditCountByDay() );
		$this->assertEqualsCanonicalizing(
			array_fill_keys( [
				'expand', 'links', 'references', 'update', 'copyedit',
				'image-recommendation', 'section-image-recommendation',
				'link-recommendation', 'revise-tone',
			], 0 ),
			$userImpact->getEditCountByTaskType()
		);
		$this->assertSame( 0, $userImpact->getNewcomerTaskEditCount() );
		$this->assertNull( $userImpact->getLastEditTimestamp() );
		$this->assertSame( 0, $userImpact->getReceivedThanksCount() );
	}

	public function testGetUserImpactForTemporaryAccount() {
		$temporaryAccount = $this->getServiceContainer()->getTempUserCreator()
			->create( '~2025-1', new FauxRequest() )
			->getUser();
		/** @var ComputedUserImpactLookup $userImpactLookup */
		$userImpactLookup = $this->getServiceContainer()->get( 'GrowthExperimentsUserImpactLookup_Computed' );
		$userImpact = $userImpactLookup->getUserImpact( $temporaryAccount );
		$this->assertTrue( $temporaryAccount->equals( $userImpact->getUser() ) );
		$expensiveUserImpact = $userImpactLookup->getExpensiveUserImpact( $temporaryAccount );
		$this->assertTrue( $temporaryAccount->equals( $expensiveUserImpact->getUser() ) );
	}

	public function testGetUserImpact() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Thanks' );

		$configurationLoader = $this->createMock( ConfigurationLoader::class );
		$configurationLoader->method( 'getTaskTypes' )->willReturn( [
			'copyedit' => new TemplateBasedTaskType( 'copyedit', 'easy', [], [] ),
		] );
		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $configurationLoader );

		$status = StatusValue::newGood();
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();
		$userIdentity = $testUser->getUserIdentity();
		ConvertibleTimestamp::setFakeTime( '20221001120000' );
		$status->merge( $this->editPage( 'Foo', 'test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221001120001' );
		$status->merge( $this->editPage( 'Foo', 'another test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221001120002' );
		$status->merge( $this->editPage( 'Bar', 'yet another test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221001120003' );
		$status->merge( $this->editPage( 'Talk:Bar', 'talkspace test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221001120004' );
		$status->merge( $this->editPage( 'Baz', 'newcomer task test edit', '', NS_MAIN, $user ), true );
		ConvertibleTimestamp::setFakeTime( '20221002120000' );
		$status->merge( $this->editPage( 'Foo', 'next-day test edit', '', NS_MAIN, $user ) );
		$this->assertStatusGood( $status );
		$newcomerTaskRevision = $status->getValue()['revision-record']->getId();
		$this->getServiceContainer()->getChangeTagsStore()->addTags(
			TemplateBasedTaskTypeHandler::NEWCOMER_TASK_COPYEDIT_TAG, null, $newcomerTaskRevision
		);

		// Mark the page creation entry for "Baz" as deleted, so that it is not counted
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'logging' )
			->set( [ 'log_deleted' => 15 ] )
			->where( [ 'log_type' => 'create', 'log_title' => 'Baz' ] )
			->caller( __METHOD__ )
			->execute();

		ConvertibleTimestamp::setFakeTime( '20221002130000' );
		$thanker = $this->getTestUser()->getUser();
		$this->doApiRequestWithToken( [
			'action' => 'thank',
			'source' => 'diff',
			'rev' => $newcomerTaskRevision,
		], null, $thanker );
		ConvertibleTimestamp::setFakeTime( false );

		/** @var ComputedUserImpactLookup $userImpactLookup */
		$userImpactLookup = $this->getServiceContainer()->get( 'GrowthExperimentsUserImpactLookup_Computed' );
		$userImpact = $userImpactLookup->getUserImpact( $userIdentity );

		$this->assertTrue( $userIdentity->equals( $userImpact->getUser() ) );
		$this->assertSame( [ NS_MAIN => 5, NS_TALK => 1 ], $userImpact->getEditCountByNamespace() );
		$this->assertSame( [ '2022-10-01' => 5, '2022-10-02' => 1 ], $userImpact->getEditCountByDay() );
		$this->assertSame( 1, $userImpact->getNewcomerTaskEditCount() );
		$this->assertSame( (int)wfTimestamp( TS_UNIX, '20221002120000' ), $userImpact->getLastEditTimestamp() );
		$this->assertSame( 1, $userImpact->getReceivedThanksCount() );
		$this->assertSame( 0, $userImpact->getGivenThanksCount() );
		$this->assertSame( 2, $userImpact->getTotalArticlesCreatedCount() );
		$thankerUserImpact = $userImpactLookup->getUserImpact( $thanker );
		$this->assertSame( 1, $thankerUserImpact->getGivenThanksCount() );
	}

	public function testGetUserImpact_offset() {
		$status = StatusValue::newGood();
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();
		$userIdentity = $testUser->getUserIdentity();

		// UTC+10
		$this->getServiceContainer()->getUserOptionsManager()->setOption( $userIdentity, 'timecorrection',
			'ZoneInfo|600|Australia/Sydney' );
		// UTC+2
		$this->overrideConfigValues( [
			MainConfigNames::Localtimezone => 'UTC',
			MainConfigNames::LocalTZoffset => 120,
		] );
		// Days should be computed using wikis defaults rather than user preference to avoid sensitive user data leak
		ConvertibleTimestamp::setFakeTime( '20221001040000' );
		$status->merge( $this->editPage( 'Test 1', 'test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221001050000' );
		$status->merge( $this->editPage( 'Test 2', 'test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221001160000' );
		$status->merge( $this->editPage( 'Test 3', 'test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221001230000' );
		$status->merge( $this->editPage( 'Test 4', 'test edit', '', NS_MAIN, $user ) );

		/** @var ComputedUserImpactLookup $userImpactLookup */
		$userImpactLookup = $this->getServiceContainer()->get( 'GrowthExperimentsUserImpactLookup_Computed' );
		$userImpact = $userImpactLookup->getUserImpact( $userIdentity );

		$this->assertSame( [ '2022-10-01' => 3, '2022-10-02' => 1 ], $userImpact->getEditCountByDay() );
	}

	public function testGetUserImpactExpensive_empty() {
		$this->markTestSkippedIfExtensionNotLoaded( 'PageViewInfo' );

		$userIdentity = $this->getMutableTestUser()->getUserIdentity();
		$pageViewService = $this->createNoOpMock( PageViewService::class );
		$this->setService( 'PageViewService', $pageViewService );
		/** @var ComputedUserImpactLookup $userImpactLookup */
		$userImpactLookup = $this->getServiceContainer()->get( 'GrowthExperimentsUserImpactLookup_Computed' );
		ConvertibleTimestamp::setFakeTime( '2022-10-30 12:00:00' );
		$userImpact = $userImpactLookup->getExpensiveUserImpact( $userIdentity );

		$this->assertTrue( $userIdentity->equals( $userImpact->getUser() ) );
		$this->assertSame( [], $userImpact->getEditCountByNamespace() );
		$this->assertSame( [], $userImpact->getEditCountByDay() );
		$this->assertSame( 0, $userImpact->getNewcomerTaskEditCount() );
		$this->assertNull( $userImpact->getLastEditTimestamp() );
		$this->assertSame( 0, $userImpact->getReceivedThanksCount() );
		$this->assertSame( [], $userImpact->getDailyArticleViews() );
		$this->assertSame( [], $userImpact->getDailyTotalViews() );
	}

	public function testGetUserImpactExpensive() {
		$this->markTestSkippedIfExtensionNotLoaded( 'PageViewInfo' );
		// For convenience in this test, set a higher request limit. Needed so that
		// the override of 'getPageData' can return the complete set of data for 6
		// articles.
		$this->overrideConfigValue( 'PageViewInfoWikimediaRequestLimit', 6 );

		$status = StatusValue::newGood();
		$testUser = $this->getMutableTestUser();
		$user = $testUser->getUser();
		$userIdentity = $testUser->getUserIdentity();
		ConvertibleTimestamp::setFakeTime( '20221001120000' );
		$status->merge( $this->editPage( 'Test 1', 'test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221002120001' );
		$status->merge( $this->editPage( 'Test 2', 'test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221003120002' );
		$status->merge( $this->editPage( 'Test 3', 'test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221004120003' );
		$status->merge( $this->editPage( 'Test 4', 'test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221005120004' );
		$status->merge( $this->editPage( 'Test 5', 'test edit', '', NS_MAIN, $user ) );
		ConvertibleTimestamp::setFakeTime( '20221006120005' );
		$status->merge( $this->editPage( 'Test 6', 'test edit', '', NS_MAIN, $user ) );
		$this->assertStatusGood( $status );

		$pageViewService = $this->createNoOpMock( PageViewService::class, [ 'getPageData' ] );
		$pageViewService->method( 'getPageData' )->willReturnCallback( function ( array $titles, int $days ) {
			// last 6 edits in chronological order
			$expectedTitles = [ 'Test 1', 'Test 2', 'Test 3', 'Test 4', 'Test 5', 'Test 6' ];
			$this->assertArrayEquals( $expectedTitles, array_map( static function ( Title $title ) {
				return $title->getPrefixedText();
			}, $titles ) );
			$this->assertSame( 60, $days );
			$data = [];
			foreach ( range( 1, 6 ) as $page ) {
				foreach ( range( 1, 31 ) as $day ) {
					$paddedDay = str_pad( $day, 2, '0', STR_PAD_LEFT );
					$data["Test $page"]["2022-10-$paddedDay"] = $page * 100 + $day;
				}
			}
			$status = StatusValue::newGood( $data );
			$status->successCount = count( $data );
			$status->success = array_fill_keys( array_keys( $data ), true );
			return $status;
		} );
		$this->setService( 'PageViewService', $pageViewService );

		/** @var ComputedUserImpactLookup $userImpactLookup */
		$userImpactLookup = $this->getServiceContainer()->get( 'GrowthExperimentsUserImpactLookup_Computed' );
		$userImpact = $userImpactLookup->getExpensiveUserImpact( $userIdentity );
		$this->assertTrue( $userIdentity->equals( $userImpact->getUser() ) );
		$this->assertSame( [ NS_MAIN => 6 ], $userImpact->getEditCountByNamespace() );
		$this->assertSame( [
			'2022-10-01' => 1,
			'2022-10-02' => 1,
			'2022-10-03' => 1,
			'2022-10-04' => 1,
			'2022-10-05' => 1,
			'2022-10-06' => 1,
		], $userImpact->getEditCountByDay() );
		$this->assertSame( 0, $userImpact->getNewcomerTaskEditCount() );
		$this->assertSame( (int)wfTimestamp( TS_UNIX, '20221006120005' ), $userImpact->getLastEditTimestamp() );
		$this->assertSame( 0, $userImpact->getReceivedThanksCount() );

		$dailyTotalViews = $userImpact->getDailyTotalViews();
		$expectedDays = array_map( static function ( $day ) {
			return '2022-10-' . str_pad( $day, 2, '0', STR_PAD_LEFT );
		}, range( 1, 31 ) );
		$this->assertSame( $expectedDays, array_keys( $dailyTotalViews ) );
		$this->assertSame( 2112, $dailyTotalViews['2022-10-02'] );
		$this->assertSame( 2280, $dailyTotalViews['2022-10-30'] );

		$dailyArticleViews = $userImpact->getDailyArticleViews();
		// The data is for a list of articles to be displayed in a top chart, with the most "top"
		// article (defined in the current implementation as the most recently edited) being at
		// top, so the most recently edited must come first.
		$this->assertSame(
			// FIXME: This should be in reverse order.
			[ 'Test_1', 'Test_2', 'Test_3', 'Test_4', 'Test_5', 'Test_6' ],
			array_keys( $dailyArticleViews )
		);
		$this->assertSame( $expectedDays, array_keys( $dailyArticleViews['Test_2']['views'] ) );
		$this->assertSame( 202, $dailyArticleViews['Test_2']['views']['2022-10-02'] );
		$this->assertSame( 530, $dailyArticleViews['Test_5']['views']['2022-10-30'] );
	}

}
