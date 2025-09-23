<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\ErrorException;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\Extension\Notifications\DbFactory;
use MediaWiki\Extension\Notifications\Mapper\NotificationMapper;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

/**
 * @group Database
 * @group Echo
 * @covers \GrowthExperiments\NewcomerTasks\MediaWikiEventIngress\NewcomerMilestoneIngress
 */
class NewcomerMilestoneIngressTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'Echo' );
		$this->overrideConfigValues( [
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
			'GENewcomerTasksStarterDifficultyEnabled' => true,
		] );
	}

	/**
	 * @return array[]
	 */
	public static function provideNotificationScenarios(): array {
		return [
			'notification sent when threshold reached' => [
				'userEditCount' => 4,
				'maxEditsThreshold' => 5,
				'addLinkEditCount' => 1,
				'expectedNotificationCount' => 1,
			],
			'no notification when no add-link edits' => [
				'userEditCount' => 4,
				'maxEditsThreshold' => 5,
				'addLinkEditCount' => 0,
				'expectedNotificationCount' => 0,
			],
			'no notification when threshold not reached' => [
				'userEditCount' => 3,
				'maxEditsThreshold' => 5,
				'addLinkEditCount' => 1,
				'expectedNotificationCount' => 0,
			],
			'no notification when threshold not set' => [
				'userEditCount' => 4,
				'maxEditsThreshold' => null,
				'addLinkEditCount' => 1,
				'expectedNotificationCount' => 0,
			],
		];
	}

	/**
	 * @dataProvider provideNotificationScenarios
	 */
	public function testMilestoneNotification(
		int $userEditCount,
		?int $maxEditsThreshold,
		int $addLinkEditCount,
		int $expectedNotificationCount
	): void {
		$this->clearEchoData();
		$this->setMaxEditsTaskIsAvailableInConfig( $maxEditsThreshold ? (string)$maxEditsThreshold : 'no' );
		$user = $this->getMutableTestUser()->getUser();
		$this->setUserEditCount( $user, $userEditCount );
		if ( $addLinkEditCount > 0 ) {
			// Simulate an addlink edit
			$page = $this->getExistingTestPage();
			$pageUpdater = $page->newPageUpdater( $user );
			$pageUpdater->setContent( SlotRecord::MAIN, new WikitextContent( 'newcomer edit' ) );
			$pageUpdater->addTags( [ 'newcomer task add link' ] );
			$pageUpdater->saveRevision( CommentStoreComment::newUnsavedComment( 'edit by newcomer' ) );
		} else {
			// Simulate a random edit
			$this->editPage( 'Test', 'Test-edit-1', 'Test', NS_MAIN, $user );
		}

		$notificationMapper = new NotificationMapper();
		$notifications = $notificationMapper->fetchByUser(
			$user,
			10,
			null,
			[ 'newcomer-milestone-reached' ]
		);

		$this->assertCount(
			$expectedNotificationCount,
			$notifications,
			"Expected $expectedNotificationCount notification(s) for milestone threshold"
		);
	}

	/**
	 * @throws ErrorException
	 */
	private function setMaxEditsTaskIsAvailableInConfig( string $selectedEnumValue = 'no' ): void {
		$communityConfigServices = CommunityConfigurationServices::wrap( $this->getServiceContainer() );
		$suggestedEditsProvider = $communityConfigServices
			->getConfigurationProviderFactory()->newProvider( 'GrowthSuggestedEdits' );
		$status = $suggestedEditsProvider->loadValidConfiguration();
		$config = null;
		if ( $status->isOK() ) {
			$config = $status->getValue();
			$config->{'link_recommendation'}->{'maximumEditsTaskIsAvailable'} = $selectedEnumValue;
		}
		$status = $suggestedEditsProvider->storeValidConfiguration(
			$config, $this->getTestUser( [ 'interface-admin' ] )->getUser()
		);
		if ( !$status->isOK() ) {
			throw new ErrorException( $status );
		}
	}

	private function setUserEditCount( User $user, int $editCount ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'user' )
			->set( [ 'user_editcount' => $editCount ] )
			->where( [ 'user_id' => $user->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	private function clearEchoData(): void {
		$db = DbFactory::newFromDefault()->getEchoDb( DB_PRIMARY );
		$db->newDeleteQueryBuilder()
			->deleteFrom( 'echo_event' )
			->where( ISQLPlatform::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
		$db->newDeleteQueryBuilder()
			->deleteFrom( 'echo_notification' )
			->where( ISQLPlatform::ALL_ROWS )
			->caller( __METHOD__ )
			->execute();
	}
}
