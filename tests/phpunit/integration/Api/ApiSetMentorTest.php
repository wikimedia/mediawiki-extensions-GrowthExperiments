<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\DatabaseMentorStore;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use PHPUnit\Framework\Constraint\Constraint;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers \GrowthExperiments\Api\ApiSetMentor
 */
class ApiSetMentorTest extends ApiTestCase {

	public function testRequiredParams() {
		$this->expectApiErrorCode( 'missingparam' );

		$this->doApiRequestWithToken(
			[ 'action' => 'growthsetmentor' ],
			null,
			$this->getTestSysop()->getUser()
		);
	}

	public function testAnonCannotSetMentor() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();

		// Ensure no mentor changes are made
		$this->setService( 'GrowthExperimentsMentorStore', $this->createNoOpMock( MentorStore::class ) );

		$this->expectApiErrorCode( 'permissiondenied' );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName(),
			],
			null,
			new User()
		);
	}

	public function testNormalUserCannotSetMentor() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$performer = $this->getMutableTestUser()->getUser();

		// Ensure no mentor changes are made
		$this->setService( 'GrowthExperimentsMentorStore', $this->createNoOpMock( MentorStore::class ) );

		$this->expectApiErrorCode( 'permissiondenied' );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName(),
			],
			null,
			$performer
		);
	}

	public function testSetMentorBySysop() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();
		$mockMentorStore = $this->getMockMentorStore( $mentee, $mentor );
		$mockMentorStore->expects( $this->once() )
			->method( 'setMentorForUser' )
			->with( $this->ruleUserEquals( $mentee ), $this->ruleUserEquals( $mentor ) );
		$this->setService( 'GrowthExperimentsMentorStore', $mockMentorStore );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName(),
			],
			null,
			$this->getTestSysop()->getUser()
		);
		$this->assertEquals( 'ok', $response[0]['growthsetmentor']['status'] );
	}

	public function testSetMentorByMenteeWhenMentorRegistered() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter  = $geServices->getMentorWriter();
		$this->assertStatusOK( $mentorWriter->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $mentor ),
			$this->getTestSysop()->getUserIdentity(),
			''
		) );

		$mockMentorStore = $this->getMockMentorStore( $mentee, $mentor );
		$mockMentorStore->expects( $this->once() )
			->method( 'setMentorForUser' )
			->with( $this->ruleUserEquals( $mentee ), $this->ruleUserEquals( $mentor ) );
		$this->setService( 'GrowthExperimentsMentorStore', $mockMentorStore );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName(),
			],
			null,
			$mentee
		);
		$this->assertEquals( 'ok', $response[0]['growthsetmentor']['status'] );
	}

	public function testSetMentorByMenteeWhenMentorNormalUser() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();

		// Ensure no mentor changes are made
		$this->setService( 'GrowthExperimentsMentorStore', $this->createNoOpMock( MentorStore::class ) );

		$this->expectApiErrorCode( 'permissiondenied' );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName(),
			],
			null,
			$mentee
		);
	}

	public function testSetMentorByMentorWhenRegistered() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();

		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$mentorProvider = $geServices->getMentorProvider();
		$mentorWriter  = $geServices->getMentorWriter();
		$this->assertStatusOK( $mentorWriter->addMentor(
			$mentorProvider->newMentorFromUserIdentity( $mentor ),
			$this->getTestSysop()->getUserIdentity(),
			''
		) );

		$mockMentorStore = $this->getMockMentorStore( $mentee, $mentor );
		$mockMentorStore->expects( $this->once() )
			->method( 'setMentorForUser' )
			->with( $this->ruleUserEquals( $mentee ), $this->ruleUserEquals( $mentor ) );
		$this->setService( 'GrowthExperimentsMentorStore', $mockMentorStore );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName(),
			],
			null,
			$mentor
		);
		$this->assertEquals( 'ok', $response[0]['growthsetmentor']['status'] );
	}

	public function testSetMentorByMentorWhenNormalUser() {
		$mentee = $this->getMutableTestUser()->getUser();
		$mentor = $this->getMutableTestUser()->getUser();

		// Ensure no mentor changes are made
		$this->setService( 'GrowthExperimentsMentorStore', $this->createNoOpMock( MentorStore::class ) );

		$this->expectApiErrorCode( 'permissiondenied' );
		$response = $this->doApiRequestWithToken(
			[
				'action' => 'growthsetmentor',
				'mentor' => $mentor->getName(),
				'mentee' => $mentee->getName(),
			],
			null,
			$mentor
		);
	}

	private function getMockMentorStore( UserIdentity $mentee, UserIdentity $mentor ) {
		$mock = $this->getMockBuilder( DatabaseMentorStore::class )
			->setConstructorArgs( [
				new NullLogger(),
				new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ),
				$this->createMock( UserFactory::class ),
				$this->createMock( UserIdentityLookup::class ),
				$this->createMock( JobQueueGroup::class ),
				$this->createNoOpMock( ILoadBalancer::class ),
				true,
				true,
			] )
			->onlyMethods( [ 'setMentorForUser', 'loadMentorUser' ] )
			->getMock();
		$mock->method( 'loadMentorUser' )
			->willReturn( null );
		return $mock;
	}

	private function ruleUserEquals( UserIdentity $user ) {
		return new class( $user ) extends Constraint {
			private UserIdentity $user;

			public function __construct( UserIdentity $user ) {
				$this->user = $user;
			}

			public function toString(): string {
				return 'is the same as ' . $this->user;
			}

			protected function matches( $other ): bool {
				return $other instanceof UserIdentity && $this->user->equals( $other );
			}
		};
	}

}
