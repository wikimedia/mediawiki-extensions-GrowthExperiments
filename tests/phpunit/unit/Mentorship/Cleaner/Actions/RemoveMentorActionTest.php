<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\Cleaner\Actions\RemoveMentorAction;
use GrowthExperiments\Mentorship\Cleaner\LastActionTimestampLookup;
use GrowthExperiments\Mentorship\MentorRemover;
use LogicException;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\Mentorship\Cleaner\Actions\RemoveMentorAction
 */
class RemoveMentorActionTest extends MediaWikiUnitTestCase {

	private const NOW = '20220101120000';

	protected function tearDown(): void {
		ConvertibleTimestamp::setFakeTime( false );
		parent::tearDown();
	}

	private function newAction(
		?MentorRemover $mentorRemover = null,
		?LastActionTimestampLookup $lastActionTimestampLookup = null,
		bool $isEnabled = true,
		int $minDaysSinceLastEdit = 30
	): RemoveMentorAction {
		return new RemoveMentorAction(
			$mentorRemover ?? $this->createNoOpMock( MentorRemover::class ),
			$lastActionTimestampLookup ?? $this->createNoOpMock( LastActionTimestampLookup::class ),
			new UserIdentityValue( 42, 'SystemPerformer' ),
			$isEnabled,
			$minDaysSinceLastEdit
		);
	}

	public function testIsEnabled() {
		$this->assertTrue( $this->newAction( isEnabled: true )->isEnabled() );
		$this->assertFalse( $this->newAction( isEnabled: false )->isEnabled() );
	}

	public function testCheckNoLastEdit() {
		$user = new UserIdentityValue( 1, 'Mentor' );
		$lookup = $this->createMock( LastActionTimestampLookup::class );
		$lookup->expects( $this->once() )
			->method( 'getLastActionTimestampForUser' )
			->with( $user )
			->willReturn( null );

		$this->assertTrue(
			$this->newAction( lastActionTimestampLookup: $lookup )->check( $user ),
			'A mentor with no recorded action should be eligible'
		);
	}

	/**
	 * @dataProvider provideCheckTimestamps
	 */
	public function testCheck( string $lastEditTimestamp, int $minDaysSinceLastEdit, bool $expected ) {
		ConvertibleTimestamp::setFakeTime( self::NOW );
		$user = new UserIdentityValue( 1, 'Mentor' );
		$lookup = $this->createMock( LastActionTimestampLookup::class );
		$lookup->method( 'getLastActionTimestampForUser' )
			->willReturn( $lastEditTimestamp );

		$action = $this->newAction(
			lastActionTimestampLookup: $lookup,
			minDaysSinceLastEdit: $minDaysSinceLastEdit
		);
		$this->assertSame( $expected, $action->check( $user ) );
	}

	public static function provideCheckTimestamps() {
		return [
			'edited well within the window is not eligible' => [ '20211231120000', 30, false ],
			'edited exactly on the boundary is not eligible' => [ '20211202120000', 30, false ],
			'edited just over the window is eligible' => [ '20211201000000', 30, true ],
			'edited long ago is eligible' => [ '20210101120000', 30, true ],
		];
	}

	public function testCheckEditedInFuture() {
		ConvertibleTimestamp::setFakeTime( self::NOW );
		$user = new UserIdentityValue( 1, 'Mentor' );
		$lookup = $this->createMock( LastActionTimestampLookup::class );
		$lookup->method( 'getLastActionTimestampForUser' )
			->willReturn( '20220201120000' );

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( 'Mentor edited in the future' );
		$this->newAction( lastActionTimestampLookup: $lookup )->check( $user );
	}

	public function testPerform() {
		$user = new UserIdentityValue( 1, 'Mentor' );
		$status = StatusValue::newGood();

		$message = $this->createMock( Message::class );
		$message->method( 'params' )->willReturnSelf();
		$message->method( 'numParams' )->willReturnSelf();
		$message->method( 'inContentLanguage' )->willReturnSelf();
		$message->method( 'text' )->willReturn( 'the reason' );

		$messageLocalizer = $this->createMock( MessageLocalizer::class );
		$messageLocalizer->expects( $this->once() )
			->method( 'msg' )
			->with( 'growthexperiments-mentor-list-cleaner-remove-mentor-action' )
			->willReturn( $message );

		$mentorRemover = $this->createMock( MentorRemover::class );
		$mentorRemover->expects( $this->once() )
			->method( 'removeMentor' )
			->with(
				$this->callback( static fn ( UserIdentity $performer ) => $performer->getName() === 'SystemPerformer' ),
				$user,
				'the reason',
				$messageLocalizer
			)
			->willReturn( $status );

		$action = $this->newAction( mentorRemover: $mentorRemover );
		$this->assertSame( $status, $action->perform( $user, $messageLocalizer ) );
	}
}
