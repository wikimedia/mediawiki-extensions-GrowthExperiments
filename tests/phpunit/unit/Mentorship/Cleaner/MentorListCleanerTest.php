<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Cleaner\Actions\ActionFactory;
use GrowthExperiments\Mentorship\Cleaner\Actions\IAction;
use GrowthExperiments\Mentorship\Cleaner\Actions\MarkMentorAsAwayAction;
use GrowthExperiments\Mentorship\Cleaner\Actions\RemoveMentorAction;
use GrowthExperiments\Mentorship\Cleaner\MentorListCleaner;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use StatusValue;

/**
 * @covers \GrowthExperiments\Mentorship\Cleaner\MentorListCleaner
 */
class MentorListCleanerTest extends MediaWikiUnitTestCase {

	public function testProcessNoEnabledAction() {
		$factory = $this->createNoOpMock( ActionFactory::class, [ 'newFromClassName' ] );
		$factory
			->expects( $this->exactly( count( ActionFactory::ACTIONS ) ) )
			->method( 'newFromClassName' )
			->willReturnCallback( function ( $className ) {
				static $idx = 0;
				$this->assertSame( ActionFactory::ACTIONS[$idx++], $className );

				$action = $this->createNoOpMock( IAction::class, [ 'isEnabled' ] );
				$action->expects( $this->once() )
					->method( 'isEnabled' )
					->willReturn( false );
				return $action;
			} );
		$mentorProvider = $this->createNoOpMock( MentorProvider::class, [ 'getMentors' ] );
		$mentorProvider->expects( $this->once() )
			->method( 'getMentors' )
			->willReturn( [] );

		$cleaner = new MentorListCleaner(
			$factory,
			$mentorProvider,
			new NullLogger()
		);
		$this->assertStatusOK( $cleaner->processMentors(
			$this->createNoOpMock( MessageLocalizer::class )
		) );
	}

	public function testProcessMentorsEnabled() {
		$messageLocalizer = $this->createNoOpMock( MessageLocalizer::class );
		$status = StatusValue::newFatal( 'error' );
		$mentorUser = new UserIdentityValue( 1, 'Admin' );
		$mentor = new Mentor( $mentorUser, null, '', IMentorWeights::WEIGHT_NORMAL );
		$factory = $this->createNoOpMock( ActionFactory::class, [ 'newFromClassName' ] );
		$factory
			->expects( $this->exactly( count( ActionFactory::ACTIONS ) ) )
			->method( 'newFromClassName' )
			->willReturnCallback( function ( $className ) use ( $mentorUser, $messageLocalizer, $status ) {
				if ( $className === MarkMentorAsAwayAction::class ) {
					$action = $this->createMock( IAction::class );
					$action->expects( $this->once() )
						->method( 'isEnabled' )
						->willReturn( true );
					$action->expects( $this->once() )
						->method( 'check' )
						->with( $mentorUser )
						->willReturn( false );
					$action->expects( $this->never() )
						->method( 'perform' );
					return $action;
				} elseif ( $className === RemoveMentorAction::class ) {
					$action = $this->createMock( IAction::class );
					$action->expects( $this->once() )
						->method( 'isEnabled' )
						->willReturn( true );
					$action->expects( $this->once() )
						->method( 'check' )
						->with( $mentorUser )
						->willReturn( true );
					$action->expects( $this->once() )
						->method( 'perform' )
						->with( $mentorUser, $messageLocalizer )
						->willReturn( $status );
					return $action;
				}
			} );

		$mentorProvider = $this->createNoOpMock( MentorProvider::class, [ 'getMentors', 'isMentor' ] );
		$mentorProvider->expects( $this->once() )
			->method( 'getMentors' )
			->willReturn( [ $mentor ] );
		$mentorProvider->expects( $this->exactly( count( ActionFactory::ACTIONS ) ) )
			->method( 'isMentor' )
			->with( $mentorUser )
			->willReturn( true );

		$cleaner = new MentorListCleaner(
			$factory,
			$mentorProvider,
			new NullLogger()
		);
		$this->assertStatusMessagesExactly( $status, $cleaner->processMentors( $messageLocalizer ) );
	}

	public function testProcessMentorsSkipsRemovedMentor() {
		$messageLocalizer = $this->createNoOpMock( MessageLocalizer::class );
		$mentorUser = new UserIdentityValue( 1, 'Admin' );
		$mentor = new Mentor( $mentorUser, null, '', IMentorWeights::WEIGHT_NORMAL );

		$removeAction = $this->createMock( IAction::class );
		$removeAction->method( 'isEnabled' )->willReturn( true );
		$removeAction->expects( $this->once() )
			->method( 'check' )
			->with( $mentorUser )
			->willReturn( true );
		$removeAction->expects( $this->once() )
			->method( 'perform' )
			->with( $mentorUser, $messageLocalizer )
			->willReturn( StatusValue::newGood() );

		$markAsAwayAction = $this->createMock( IAction::class );
		$markAsAwayAction->method( 'isEnabled' )->willReturn( true );
		$markAsAwayAction->expects( $this->never() )->method( 'check' );
		$markAsAwayAction->expects( $this->never() )->method( 'perform' );

		$factory = $this->createNoOpMock( ActionFactory::class, [ 'newFromClassName' ] );
		$factory->method( 'newFromClassName' )
			->willReturnMap( [
				[ RemoveMentorAction::class, $removeAction ],
				[ MarkMentorAsAwayAction::class, $markAsAwayAction ],
			] );

		$mentorProvider = $this->createNoOpMock( MentorProvider::class, [ 'getMentors', 'isMentor' ] );
		$mentorProvider->method( 'getMentors' )->willReturn( [ $mentor ] );
		// The mentor is removed by RemoveMentorAction, so they are not a mentor afterwards
		$mentorProvider->expects( $this->exactly( 2 ) )
			->method( 'isMentor' )
			->with( $mentorUser )
			->willReturnOnConsecutiveCalls( true, false );

		$cleaner = new MentorListCleaner(
			$factory,
			$mentorProvider,
			new NullLogger()
		);
		$this->assertStatusGood( $cleaner->processMentors( $messageLocalizer ) );
	}
}
