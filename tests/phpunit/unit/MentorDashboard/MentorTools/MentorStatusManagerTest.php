<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @coversDefaultClass \GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager
 */
class MentorStatusManagerTest extends MediaWikiUnitTestCase {

	private function getTestMentor(): UserIdentity {
		return new UserIdentityValue(
			123,
			'TestMentor'
		);
	}

	private function getMockUserFactory( bool $userIsBlocked = false ): UserFactory {
		$userIdentity = $this->getTestMentor();
		$user = $this->createMock( User::class );
		$user->method( 'getName' )
			->willReturn( $userIdentity->getName() );
		$user->method( 'getId' )
			->willReturn( $userIdentity->getId() );

		if ( $userIsBlocked ) {
			$block = $this->createMock( AbstractBlock::class );
			$block->method( 'isSitewide' )
				->willReturn( true );
			$user->method( 'getBlock' )
				->willReturn( $block );
		}

		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromUserIdentity' )
			->with( $userIdentity )
			->willReturn( $user );
		return $userFactory;
	}

	/**
	 * @param string|null $rawTS
	 * @param bool $isMentorBlocked
	 * @return MentorStatusManager
	 */
	private function getMentorStatusManager(
		?string $rawTS,
		bool $isMentorBlocked
	): MentorStatusManager {
		$mentor = $this->getTestMentor();

		$userOptionsManager = $this->createMock( UserOptionsManager::class );
		$userOptionsManager->expects( $isMentorBlocked ? $this->never() : $this->atLeastOnce() )
			->method( 'getOption' )
			->with( $mentor, MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF )
			->willReturn( $rawTS );

		return new MentorStatusManager(
			$userOptionsManager,
			$this->createMock( UserIdentityLookup::class ),
			$this->getMockUserFactory( $isMentorBlocked ),
			$this->createMock( IConnectionProvider::class )
		);
	}

	/**
	 * @covers ::getMentorBackTimestamp
	 * @dataProvider provideTimestamps
	 * @param string|null $expectedTS
	 * @param string|null $rawTS
	 * @param bool $isMentorBlocked
	 */
	public function testGetMentorBackTimestamp(
		?string $expectedTS,
		?string $rawTS,
		bool $isMentorBlocked
	) {
		$this->assertEquals(
			$expectedTS,
			$this->getMentorStatusManager( $rawTS, $isMentorBlocked )
				->getMentorBackTimestamp( $this->getTestMentor() )
		);
	}

	public static function provideTimestamps(): array {
		return [
			[ null, null, false ],
			[ null, null, true ],
			[ null, '20080101000000', false ],
			[ null, '20080101000000', true ],
			[ '22221231000000', '22221231000000', false ],
			[ null, '22221231000000', true ],
		];
	}

	/**
	 * @param string|null $expectedReason
	 * @param string|null $rawTS
	 * @param bool $isMentorBlocked
	 * @covers ::getAwayReason
	 * @dataProvider provideGetAwayReason
	 */
	public function testGetAwayReason(
		?string $expectedReason,
		?string $rawTS,
		bool $isMentorBlocked
	) {
		$this->assertEquals(
			$expectedReason,
			$this->getMentorStatusManager( $rawTS, $isMentorBlocked )
				->getAwayReason( $this->getTestMentor() )
		);
	}

	public static function provideGetAwayReason(): array {
		return [
			'no block no timestamp' => [ null, null, false ],
			'expired timestamp no block' => [ null, '20080203000000', false ],
			'future timestamp no block' => [ 'timestamp', '22221231000000', false ],
			'no timestamp existing block' => [ 'block', null, true ],
			'expired timestamp existing block' => [ 'block', '20080203000000', true ],
			'future timestamp existing block' => [ 'block', '22221231000000', true ],
		];
	}

	/**
	 * @covers ::getMentorStatus
	 * @dataProvider provideTestStatuses
	 * @param string|null $expectedStatus
	 * @param string|null $rawTS
	 * @param bool $isMentorBlocked
	 */
	public function testGetMentorStatus(
		?string $expectedStatus,
		?string $rawTS,
		bool $isMentorBlocked
	) {
		$this->assertEquals(
			$expectedStatus,
			$this->getMentorStatusManager( $rawTS, $isMentorBlocked )
				->getMentorStatus( $this->getTestMentor() )
		);
	}

	public static function provideTestStatuses(): array {
		return [
			'no block no timestamp' => [ 'active', null, false ],
			'expired timestamp no block' => [ 'active', '20080203000000', false ],
			'future timestamp no block' => [ 'away', '22221231000000', false ],
			'no timestamp existing block' => [ 'away', null, true ],
			'expired timestamp existing block' => [ 'away', '20080203000000', true ],
			'future timestamp existing block' => [ 'away', '22221231000000', true ],
		];
	}

	/**
	 * @covers ::markMentorAsActive
	 * @param bool $isMentorBlocked
	 * @dataProvider provideMarkMentorAsActive
	 */
	public function testMarkMentorAsActive( bool $isMentorBlocked ) {
		ConvertibleTimestamp::setFakeTime( '20211001120005' );

		$mentor = $this->getTestMentor();

		$userOptionsManager = $this->createMock( UserOptionsManager::class );
		$userOptionsManager->expects( $isMentorBlocked ? $this->never() : $this->once() )
			->method( 'getOption' )
			->with( $mentor, MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF )
			->willReturn( '20221001120005' );
		$userOptionsManager->expects( $isMentorBlocked ? $this->never() : $this->once() )
			->method( 'setOption' )
			->with( $mentor, MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF, null );

		$manager = new MentorStatusManager(
			$userOptionsManager,
			$this->createMock( UserIdentityLookup::class ),
			$this->getMockUserFactory( $isMentorBlocked ),
			$this->createMock( IConnectionProvider::class )
		);

		$status = $manager->markMentorAsActive( $mentor );
		if ( !$isMentorBlocked ) {
			$this->assertStatusOK( $status );
		} else {
			$this->assertStatusError(
				'growthexperiments-mentor-dashboard-mentor-tools-mentor-status-error-cannot-be-changed-block',
				$status
			);
		}
	}

	public static function provideMarkMentorAsActive(): array {
		return [
			'not blocked' => [ false ],
			'blocked' => [ true ],
		];
	}
}
