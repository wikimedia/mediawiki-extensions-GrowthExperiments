<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Cleaner\Actions\MarkMentorAsAwayAction;
use GrowthExperiments\Mentorship\Cleaner\LastActionTimestampLookup;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use LogicException;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use StatusValue;
use Wikimedia\ParamValidator\TypeDef\ExpiryDef;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * @covers \GrowthExperiments\Mentorship\Cleaner\Actions\MarkMentorAsAwayAction
 */
class MarkMentorAsAwayActionTest extends MediaWikiUnitTestCase {

	private const NOW = '20220101120000';

	protected function tearDown(): void {
		ConvertibleTimestamp::setFakeTime( false );
		parent::tearDown();
	}

	private function newAction(
		?MentorProvider $mentorProvider = null,
		?IMentorWriter $mentorWriter = null,
		?MentorStatusManager $mentorStatusManager = null,
		?LastActionTimestampLookup $lastActionTimestampLookup = null,
		bool $isEnabled = true,
		int $minDaysSinceLastEdit = 30,
		int $awayDurationInDays = 90
	): MarkMentorAsAwayAction {
		return new MarkMentorAsAwayAction(
			$mentorProvider ?? $this->createNoOpMock( MentorProvider::class ),
			$mentorWriter ?? $this->createNoOpMock( IMentorWriter::class ),
			$mentorStatusManager ?? $this->createNoOpMock( MentorStatusManager::class ),
			$lastActionTimestampLookup ?? $this->createNoOpMock( LastActionTimestampLookup::class ),
			new UserIdentityValue( 42, 'SystemPerformer' ),
			$isEnabled,
			$minDaysSinceLastEdit,
			$awayDurationInDays
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
		ConvertibleTimestamp::setFakeTime( self::NOW );
		$awayDurationInDays = 90;
		$expectedAwayTimestamp = ExpiryDef::normalizeExpiry( sprintf( '%d days', $awayDurationInDays ) )
			->getTimestamp( TimestampFormat::MW );

		$user = new UserIdentityValue( 1, 'Mentor' );

		$mentor = $this->createMock( Mentor::class );
		$mentor->expects( $this->once() )
			->method( 'setAwayTimestamp' )
			->with( $expectedAwayTimestamp );

		$mentorProvider = $this->createMock( MentorProvider::class );
		$mentorProvider->expects( $this->once() )
			->method( 'newMentorFromUserIdentity' )
			->with( $user )
			->willReturn( $mentor );

		$message = $this->createMock( Message::class );
		$message->method( 'params' )->willReturnSelf();
		$message->method( 'numParams' )->willReturnSelf();
		$message->method( 'inContentLanguage' )->willReturnSelf();
		$message->method( 'text' )->willReturn( 'the summary' );

		$messageLocalizer = $this->createMock( MessageLocalizer::class );
		$messageLocalizer->expects( $this->once() )
			->method( 'msg' )
			->with( 'growthexperiments-mentor-list-cleaner-mark-mentor-as-away-action' )
			->willReturn( $message );

		$mentorStatusManager = $this->createMock( MentorStatusManager::class );
		$mentorStatusManager->expects( $this->once() )
			->method( 'markMentorAsAwayTimestamp' )
			->with( $user, $expectedAwayTimestamp )
			->willReturn( StatusValue::newGood() );

		$mentorWriter = $this->createMock( IMentorWriter::class );
		$mentorWriter->expects( $this->once() )
			->method( 'changeMentor' )
			->with(
				$mentor,
				$this->callback( static fn ( UserIdentity $performer ) => $performer->getName() === 'SystemPerformer' ),
				'the summary'
			)
			->willReturn( StatusValue::newGood() );

		$action = $this->newAction(
			mentorProvider: $mentorProvider,
			mentorWriter: $mentorWriter,
			mentorStatusManager: $mentorStatusManager,
			awayDurationInDays: $awayDurationInDays
		);
		$this->assertStatusGood( $action->perform( $user, $messageLocalizer ) );
	}
}
