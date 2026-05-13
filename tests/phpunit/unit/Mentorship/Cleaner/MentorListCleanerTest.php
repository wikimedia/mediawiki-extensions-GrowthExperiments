<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\Mentorship\Cleaner\Actions\ActionFactory;
use GrowthExperiments\Mentorship\Cleaner\Actions\IAction;
use GrowthExperiments\Mentorship\Cleaner\Actions\MarkMentorAsAwayAction;
use GrowthExperiments\Mentorship\Cleaner\Actions\RemoveMentorAction;
use GrowthExperiments\Mentorship\Cleaner\MentorListCleaner;
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
		$mentor = new UserIdentityValue( 1, 'Admin' );
		$factory = $this->createNoOpMock( ActionFactory::class, [ 'newFromClassName' ] );
		$factory
			->expects( $this->exactly( count( ActionFactory::ACTIONS ) ) )
			->method( 'newFromClassName' )
			->willReturnCallback( function ( $className ) use ( $mentor, $messageLocalizer, $status ) {
				if ( $className === MarkMentorAsAwayAction::class ) {
					$action = $this->createMock( IAction::class );
					$action->expects( $this->once() )
						->method( 'isEnabled' )
						->willReturn( true );
					$action->expects( $this->once() )
						->method( 'check' )
						->with( $mentor )
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
						->with( $mentor )
						->willReturn( true );
					$action->expects( $this->once() )
						->method( 'perform' )
						->with( $mentor, $messageLocalizer )
						->willReturn( $status );
					return $action;
				}
			} );

		$mentorProvider = $this->createNoOpMock( MentorProvider::class, [ 'getMentors' ] );
		$mentorProvider->expects( $this->once() )
			->method( 'getMentors' )
			->willReturn( [ $mentor ] );

		$cleaner = new MentorListCleaner(
			$factory,
			$mentorProvider,
			new NullLogger()
		);
		$this->assertStatusMessagesExactly( $status, $cleaner->processMentors( $messageLocalizer ) );
	}
}
