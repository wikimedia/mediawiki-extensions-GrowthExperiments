<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\HomepageHooks;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\Tests\Helpers\HomepageHooksHelpers;
use MediaWiki\Config\HashConfig;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\HomepageHooks
 */
class HomepageHooksTest extends MediaWikiUnitTestCase {
	use HomepageHooksHelpers;

	public function testConstruct(): void {
		$this->assertInstanceOf( HomepageHooks::class, $this->getHomepageHooksMock() );
	}

	public function testOnContributeCards(): void {
		$titleFactoryMock = $this->createMock( TitleFactory::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getLocalNameFor' )
			->willReturn( 'Homepage' );
		$homepageTitleMock = $this->createMock( Title::class );
		$homepageTitleMock->method( 'getLinkURL' )->willReturn( '/foo/bar/' );
		$titleFactoryMock->method( 'newFromLinkTarget' )
			->willReturn( $homepageTitleMock );

		$userIdentity = new UserIdentityValue( 1, 'Foo' );

		$messageLocalizerMock = $this->createMock( MessageLocalizer::class );
		$messageMock = $this->createMock( Message::class );
		$messageMock->method( 'text' )->willReturn( 'Foo' );
		$messageLocalizerMock->method( 'msg' )->willReturn( $messageMock );
		$outputPageMock = $this->createMock( OutputPage::class );

		// Scenario 1: Homepage enabled AND SuggestedEdits enabled - card should be shown
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getBoolOption' )
			->with( $userIdentity, HomepageHooks::HOMEPAGE_PREF_ENABLE )
			->willReturn( true );

		$configWithSEEnabled = new HashConfig( [
			'GEHomepageSuggestedEditsEnabled' => true,
		] );

		$homepageHooks = $this->getHomepageHooksMock(
			$configWithSEEnabled,
			$titleFactoryMock,
			$specialPageFactoryMock,
			$userOptionsLookupMock
		);
		$homepageHooks->setUserIdentity( $userIdentity );
		$homepageHooks->setMessageLocalizer( $messageLocalizerMock );
		$homepageHooks->setOutputPage( $outputPageMock );

		$cards = [];
		$homepageHooks->onContributeCards( $cards );
		$this->assertArrayEquals( [ [
			'title' => 'Foo',
			'icon' => 'lightbulb',
			'description' => 'Foo',
			'action' => [
				'action' => '/foo/bar/',
				'actionText' => 'Foo',
				'actionType' => 'link',
			] ],
		], $cards, false, true, 'Card should be shown when Homepage and SE are enabled' );

		// Scenario 2: Homepage preference disabled - no card
		$userOptionsLookupMockDisabled = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMockDisabled->method( 'getBoolOption' )
			->with( $userIdentity, HomepageHooks::HOMEPAGE_PREF_ENABLE )
			->willReturn( false );

		$homepageHooks = $this->getHomepageHooksMock(
			$configWithSEEnabled,
			$titleFactoryMock,
			$specialPageFactoryMock,
			$userOptionsLookupMockDisabled
		);
		$homepageHooks->setUserIdentity( $userIdentity );
		$homepageHooks->setMessageLocalizer( $messageLocalizerMock );
		$homepageHooks->setOutputPage( $outputPageMock );

		$cards = [];
		$homepageHooks->onContributeCards( $cards );
		$this->assertArrayEquals( [], $cards, false, true,
			'No card should be shown when Homepage preference is disabled' );

		// Scenario 3: Homepage enabled but SuggestedEdits disabled - no card
		$configWithSEDisabled = new HashConfig( [
			'GEHomepageSuggestedEditsEnabled' => false,
		] );

		$homepageHooks = $this->getHomepageHooksMock(
			$configWithSEDisabled,
			$titleFactoryMock,
			$specialPageFactoryMock,
			$userOptionsLookupMock
		);
		$homepageHooks->setUserIdentity( $userIdentity );
		$homepageHooks->setMessageLocalizer( $messageLocalizerMock );
		$homepageHooks->setOutputPage( $outputPageMock );

		$cards = [];
		$homepageHooks->onContributeCards( $cards );
		$this->assertArrayEquals( [], $cards, false, true,
			'No card should be shown when SuggestedEdits is disabled even if Homepage is enabled' );
	}

	public static function provideIsSuggestedTask(): iterable {
		yield 'page is a task' => [ true, [] ];
		yield 'page is not a task' => [
			false,
			[ 'mediawiki.GrowthExperiments.suggested_edit_link_not_a_task_total:1|c|#task_type:revise_tone' ],
		];
	}

	/**
	 * @dataProvider provideIsSuggestedTask
	 */
	public function testIsSuggestedTask( bool $isTask, array $expectedMetrics ): void {
		$taskType = new TaskType( 'revise-tone', TaskType::DIFFICULTY_EASY );
		$title = new TitleValue( NS_MAIN, 'Dhaka_Mail' );
		$user = new UserIdentityValue( 1, 'Newcomer' );

		$taskSuggester = $this->createMock( TaskSuggester::class );
		$taskSuggester->expects( $this->once() )
			->method( 'filter' )
			->willReturnCallback( function ( $filterUser, TaskSet $taskSet ) use ( $user, $isTask ) {
				$this->assertSame( $user, $filterUser );
				$this->assertSame( [ 'revise-tone' ], $taskSet->getFilters()->getTaskTypeFilters() );
				$this->assertSame( 'Dhaka_Mail', $taskSet[0]->getTitle()->getDBkey() );
				return new TaskSet( $isTask ? iterator_to_array( $taskSet ) : [], 1, 0, $taskSet->getFilters() );
			} );
		$taskSuggesterFactory = $this->createMock( TaskSuggesterFactory::class );
		$taskSuggesterFactory->method( 'create' )->willReturn( $taskSuggester );
		$statsHelper = StatsFactory::newUnitTestingHelper();

		$homepageHooks = TestingAccessWrapper::newFromObject( $this->getHomepageHooksMock(
			null, null, null, null, null, $taskSuggesterFactory, $statsHelper->getStatsFactory()
		) );

		$this->assertSame( $isTask, $homepageHooks->isSuggestedTask( $user, $taskType, $title, 'smartedit' ) );
		$this->assertSame( $expectedMetrics, $statsHelper->consumeAllFormatted() );
	}

	public static function provideCheckClickIdFormat(): iterable {
		yield 'page view token' => [ 'c5mq3pjo9l5qqjc2k34vhe5v5qn7r3uh', [] ];
		yield 'random session ID' => [ '0a1b2c3d4e5f60718293', [] ];
		yield 'made by hand' => [
			'smartedit',
			[ 'mediawiki.GrowthExperiments.suggested_edit_link_unknown_click_id_total:1|c|#task_type:revise_tone' ],
		];
		yield 'hex but too long' => [
			'0a1b2c3d4e5f607182930',
			[ 'mediawiki.GrowthExperiments.suggested_edit_link_unknown_click_id_total:1|c|#task_type:revise_tone' ],
		];
	}

	/**
	 * @dataProvider provideCheckClickIdFormat
	 */
	public function testCheckClickIdFormat( string $clickId, array $expectedMetrics ): void {
		$statsHelper = StatsFactory::newUnitTestingHelper();
		$homepageHooks = TestingAccessWrapper::newFromObject( $this->getHomepageHooksMock(
			null, null, null, null, null, null, $statsHelper->getStatsFactory()
		) );

		$homepageHooks->checkClickIdFormat( $clickId, new TaskType( 'revise-tone', TaskType::DIFFICULTY_EASY ) );

		$this->assertSame( $expectedMetrics, $statsHelper->consumeAllFormatted() );
	}
}
