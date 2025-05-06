<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandler;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 */
class LevelingUpManagerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \GrowthExperiments\LevelingUp\LevelingUpManager::shouldInviteUserAfterNormalEdit
	 */
	public function testShouldInviteUserAfterNormalEdit() {
		$this->markTestSkipped( 'T391036' );
		$this->overrideConfigValue( 'GELevelingUpManagerInvitationThresholds', [ 2, 3 ] );
		$user = $this->getMutableTestUser()->getUser();

		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$levelingUpManager = $growthServices->getLevelingUpManager();

		$this->assertFalse( $levelingUpManager->shouldInviteUserAfterNormalEdit( $user ) );
		$this->editPage( 'Test', 'Test-edit-1', 'Test', NS_MAIN, $user );
		$this->assertFalse( $levelingUpManager->shouldInviteUserAfterNormalEdit( $user ) );
		$this->editPage( 'Test', 'Test-edit-2', 'Test', NS_MAIN, $user );
		$this->assertTrue( $levelingUpManager->shouldInviteUserAfterNormalEdit( $user ) );
		$this->editPage( 'Test', 'Test-edit-3', 'Test', NS_MAIN, $user );
		$this->assertTrue( $levelingUpManager->shouldInviteUserAfterNormalEdit( $user ) );
		$this->editPage( 'Test', 'Test-edit-4', 'Test', NS_MAIN, $user );
		$this->assertFalse( $levelingUpManager->shouldInviteUserAfterNormalEdit( $user ) );
		$this->editPage( 'Test', 'Test-edit-5', 'Test', NS_MAIN, $user );
		$this->assertFalse( $levelingUpManager->shouldInviteUserAfterNormalEdit( $user ) );

		$user = $this->getMutableTestUser()->getUser();
		$status = $this->editPage( 'Test', 'Test2-edit-1', 'Test', NS_MAIN, $user );
		$revId = $status->getValue()['revision-record']->getId();
		$this->getServiceContainer()->getChangeTagsStore()->addTags( TaskTypeHandler::NEWCOMER_TASK_TAG, null, $revId );
		$this->editPage( 'Test', 'Test2-edit-2', 'Test', NS_MAIN, $user );
		$this->assertFalse( $levelingUpManager->shouldInviteUserAfterNormalEdit( $user ) );
	}

}
