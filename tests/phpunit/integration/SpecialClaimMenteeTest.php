<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\StaticMentorManager;
use GrowthExperiments\Specials\SpecialClaimMentee;
use HashConfig;
use PermissionsError;
use SpecialPageTestBase;

/**
 * @group Database
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialClaimMentee
 */
class SpecialClaimMenteeTest extends SpecialPageTestBase {
	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		return new SpecialClaimMentee(
			new HashConfig( [ 'GEHomepageMentorsList' => 'MentorsList' ] ),
			new StaticMentorManager( [] )
		);
	}

	/**
	 * @covers ::userCanExecute
	 */
	public function testNonMentorCantExecute() {
		$this->expectException( PermissionsError::class );
		$mentor = $this->getMutableTestUser()->getUser();
		$user = $this->getTestSysop()->getUser();
		$this->insertPage( 'MentorsList', '[[User:' . $mentor->getName() . ']]' );
		$this->setMwGlobals( 'wgGEHomepageMentorsList', 'MentorsList' );
		$this->executeSpecialPage( '', null, null, $user );
	}
}
