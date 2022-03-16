<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\StaticMentorManager;
use GrowthExperiments\WikiConfigException;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use MediaWikiUnitTestCase;
use User;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @coversDefaultClass \GrowthExperiments\Mentorship\StaticMentorManager
 */
class StaticMentorManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getMentorForUser
	 */
	public function testGetMentorForUser() {
		$mentor1 = new Mentor(
			$this->getUser( 'FooMentor' ),
			'text 1',
			'',
			true,
			MentorWeightManager::WEIGHT_NORMAL
		);
		$mentor2 = new Mentor(
			$this->getUser( 'BarMentor' ),
			'text 2',
			'',
			true,
			MentorWeightManager::WEIGHT_NORMAL
		);
		$mentorManager = new StaticMentorManager( [ 'Foo' => $mentor1, 'Bar' => $mentor2 ] );
		$this->assertSame( $mentor1, $mentorManager->getMentorForUser( $this->getUser( 'Foo' ) ) );
		$this->assertSame( $mentor2, $mentorManager->getMentorForUser( $this->getUser( 'Bar' ) ) );
		$this->expectException( WikiConfigException::class );
		$this->assertSame( null, $mentorManager->getMentorForUser( $this->getUser( 'Baz' ) ) );
	}

	/**
	 * @covers ::getMentorForUserSafe
	 */
	public function testGetMentorForUserSafe() {
		$mentor1 = new Mentor(
			$this->getUser( 'FooMentor' ),
			'text 1',
			'',
			true,
			MentorWeightManager::WEIGHT_NORMAL
		);
		$mentor2 = new Mentor(
			$this->getUser( 'BarMentor' ),
			'text 2',
			'',
			true,
			MentorWeightManager::WEIGHT_NORMAL
		);
		$mentorManager = new StaticMentorManager( [ 'Foo' => $mentor1, 'Bar' => $mentor2 ] );
		$this->assertSame( $mentor1, $mentorManager->getMentorForUserSafe( $this->getUser( 'Foo' ) ) );
		$this->assertSame( $mentor2, $mentorManager->getMentorForUserSafe( $this->getUser( 'Bar' ) ) );
		$this->assertSame( null, $mentorManager->getMentorForUserSafe( $this->getUser( 'Baz' ) ) );
	}

	/**
	 * Creates a mock user.
	 * @param string $name Must be properly formatted (capitalized, no underscores etc)
	 * @return User
	 */
	private function getUser( string $name ) {
		$userFactory = new UserFactory(
			$this->getMockBuilder( ILoadBalancer::class )
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder( UserNameUtils::class )
				->disableOriginalConstructor()
				->getMock()
		);
		return $userFactory->newFromAnyId( null, $name, null );
	}

}
