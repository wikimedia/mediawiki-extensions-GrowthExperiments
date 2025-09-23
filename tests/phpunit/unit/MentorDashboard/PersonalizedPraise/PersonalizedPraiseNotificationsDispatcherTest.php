<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\EventLogging\PersonalizedPraiseLogger;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseNotificationsDispatcher;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseSettings;
use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\PersonalizedPraise\PersonalizedPraiseNotificationsDispatcher
 */
class PersonalizedPraiseNotificationsDispatcherTest extends MediaWikiUnitTestCase {

	private UserIdentity $mentorUser;
	private UserIdentity $menteeUser;

	/** @inheritDoc */
	protected function setUp(): void {
		parent::setUp();

		$this->mentorUser = new UserIdentityValue( 1, 'Mentor' );
		$this->menteeUser = new UserIdentityValue( 2, 'Mentee' );
	}

	private function getConfig(): Config {
		return new HashConfig( [
			'GEPersonalizedPraiseNotificationsEnabled' => true,
		] );
	}

	private function getMockPersonalizedPraiseSettings( int $frequency = 168, bool $isCalled = true ) {
		$personalizedPraiseSettingsMock = $this->createMock( PersonalizedPraiseSettings::class );
		$personalizedPraiseSettingsMock->expects( $isCalled ? $this->once() : $this->never() )
			->method( 'getNotificationsFrequency' )
			->with( $this->mentorUser )
			->willReturn( $frequency );
		return $personalizedPraiseSettingsMock;
	}

	private function getMockPersonalizedPraiseLogger( bool $doesLog ) {
		$personalizedPraiseLoggerMock = $this->createMock( PersonalizedPraiseLogger::class );
		$personalizedPraiseLoggerMock->expects( $doesLog ? $this->once() : $this->never() )
			->method( 'logNotified' )
			->with( $this->mentorUser );
		return $personalizedPraiseLoggerMock;
	}

	/**
	 * @covers ::__construct
	 * @covers ::onMenteeSuggested
	 * @covers ::getLastNotified
	 * @covers ::doesMentorHavePendingMentees
	 * @dataProvider provideOnMenteeSuggested
	 * @param int $frequency
	 * @param string $consequence One of 'notification', 'pending' or 'none'
	 */
	public function testOnMenteeSuggested(
		int $frequency,
		string $consequence
	) {
		ConvertibleTimestamp::setFakeTime( '20230601123100' );

		$dispatcher = new PersonalizedPraiseNotificationsDispatcher(
			$this->getConfig(),
			new HashBagOStuff(),
			$this->createNoOpMock( SpecialPageFactory::class ),
			$this->getMockPersonalizedPraiseSettings( $frequency ),
			$this->getMockPersonalizedPraiseLogger( $consequence === 'notification' )
		);
		$dispatcherTesting = TestingAccessWrapper::newFromObject( $dispatcher );

		$dispatcher->onMenteeSuggested( $this->mentorUser, $this->menteeUser );

		switch ( $consequence ) {
			case 'notification':
				$this->assertSame( '20230601123100', $dispatcherTesting->getLastNotified( $this->mentorUser ) );
				$this->assertFalse( $dispatcherTesting->doesMentorHavePendingMentees( $this->mentorUser ) );
				break;
			case 'pending':
				$this->assertNull( $dispatcherTesting->getLastNotified( $this->mentorUser ) );
				$this->assertTrue( $dispatcherTesting->doesMentorHavePendingMentees( $this->mentorUser ) );
				break;
			case 'none':
				$this->assertNull( $dispatcherTesting->getLastNotified( $this->mentorUser ) );
				$this->assertFalse( $dispatcherTesting->doesMentorHavePendingMentees( $this->mentorUser ) );
				break;
			default:
				throw new LogicException( 'Unrecognized value passed as $consequence' );
		}
	}

	public static function provideOnMenteeSuggested() {
		return [
			'immediately' => [ PersonalizedPraiseSettings::NOTIFY_IMMEDIATELY, 'notification' ],
			'never' => [ PersonalizedPraiseSettings::NOTIFY_NEVER, 'none' ],
			'168' => [ 168, 'pending' ],
		];
	}

	/**
	 * @covers ::maybeNotifyAboutPendingMentees
	 * @dataProvider provideMaybeNotifyAboutPendingMentees
	 * @param bool $expectedNotify
	 * @param bool $hasPending
	 * @param string|null $lastNotification
	 * @param int $frequency
	 */
	public function testMaybeNotifyAboutPendingMentees(
		bool $expectedNotify,
		bool $hasPending,
		?string $lastNotification,
		int $frequency
	) {
		ConvertibleTimestamp::setFakeTime( '20230601123100' );

		$dispatcher = new PersonalizedPraiseNotificationsDispatcher(
			$this->getConfig(),
			new HashBagOStuff(),
			$this->createNoOpMock( SpecialPageFactory::class ),
			$this->getMockPersonalizedPraiseSettings( $frequency, $hasPending ),
			$this->getMockPersonalizedPraiseLogger( $expectedNotify )
		);
		$dispatcherTesting = TestingAccessWrapper::newFromObject( $dispatcher );

		if ( $hasPending ) {
			$dispatcherTesting->markMenteeAsPendingForMentor( $this->mentorUser, $this->menteeUser );
		}
		if ( $lastNotification ) {
			$dispatcherTesting->setLastNotified( $this->mentorUser, $lastNotification );
		}

		$this->assertSame(
			$expectedNotify,
			$dispatcher->maybeNotifyAboutPendingMentees( $this->mentorUser )
		);

		if ( $expectedNotify ) {
			$this->assertFalse( $dispatcherTesting->doesMentorHavePendingMentees( $this->mentorUser ) );
			$this->assertSame( '20230601123100', $dispatcherTesting->getLastNotified( $this->mentorUser ) );
		} else {
			$this->assertSame(
				$hasPending,
				$dispatcherTesting->doesMentorHavePendingMentees( $this->mentorUser )
			);
			$this->assertSame(
				$lastNotification,
				$dispatcherTesting->getLastNotified( $this->mentorUser )
			);
		}
	}

	public static function provideMaybeNotifyAboutPendingMentees() {
		return [
			'immediately, no pending' => [ false, false, null, PersonalizedPraiseSettings::NOTIFY_IMMEDIATELY ],
			'never, no pending' => [ false, false, null, PersonalizedPraiseSettings::NOTIFY_NEVER ],
			'168, no pending' => [ false, false, null, 168 ],
			'168, has pending, first notif' => [ true, true, null, 168 ],
			'168, has pending, notif' => [ true, true, '20220601000000', 168 ],
			'1, has pending, early notif' => [ false, true, '20230601113101', 1, false ],
			'immediately, has pending' => [ true, true, null, PersonalizedPraiseSettings::NOTIFY_IMMEDIATELY ],
		];
	}
}
