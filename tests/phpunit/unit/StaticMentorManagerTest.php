<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\StaticMentorManager;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \GrowthExperiments\Mentorship\StaticMentorManager
 */
class StaticMentorManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @var ?Mentor
	 */
	protected ?Mentor $mentor1 = null;

	/**
	 * @var ?Mentor
	 */
	protected ?Mentor $mentor2 = null;

	protected function setUp(): void {
		$this->mentor1 = new Mentor(
			new UserIdentityValue( 12, 'FooMentor' ),
			'text 1',
			'',
			IMentorWeights::WEIGHT_NORMAL
		);
		$this->mentor2 = new Mentor(
			new UserIdentityValue( 13, 'BarMentor' ),
			'text 2',
			'',
			IMentorWeights::WEIGHT_NORMAL
		);
	}

	public function testGetMentorForUserSafe() {
		$mentorManager = new StaticMentorManager( [ 'Foo' => $this->mentor1, 'Bar' => $this->
		mentor2,
		] );
		$this->assertSame( $this->mentor1,
			$mentorManager->getMentorForUserSafe( new UserIdentityValue( 21, 'Foo' ) ) );
		$this->assertSame( $this->mentor2,
			$mentorManager->getMentorForUserSafe( new UserIdentityValue( 22, 'Bar' ) ) );
		$this->assertSame( null,
			$mentorManager->getMentorForUserSafe( new UserIdentityValue( 23, 'Baz' ) ) );
	}
}
