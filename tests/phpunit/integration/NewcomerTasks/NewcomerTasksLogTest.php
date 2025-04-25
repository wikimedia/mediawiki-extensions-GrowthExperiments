<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @group medium
 */
class NewcomerTasksLogTest extends MediaWikiIntegrationTestCase {

	private function logNewcomerTasks( int $tasks, UserIdentity $user, string $taskType ) {
		for ( $i = 0; $i < $tasks; $i++ ) {
			$logEntry = new ManualLogEntry( 'growthexperiments', $taskType );
			$logEntry->setTarget( Title::newFromText( 'BlankPage', NS_SPECIAL ) );
			$logEntry->setPerformer( $user );
			$logEntry->setParameters( [
				'accepted' => 0,
			] );
			$logId = $logEntry->insert();
			$logEntry->publish( $logId );
		}
	}

	public static function provideTestCount() {
		return [
			[ 0 ],
			[ 5 ],
			[ 10 ]
		];
	}

	/**
	 * @param int $tasks
	 * @dataProvider provideTestCount
	 * @covers \GrowthExperiments\NewcomerTasks\NewcomerTasksLog
	 * @covers \GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationSubmissionLogFactory
	 */
	public function testCountLinks( int $tasks ) {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$this->logNewcomerTasks( $tasks, $user, 'addlink' );

		$this->assertSame(
			$tasks,
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getLinkRecommendationSubmissionLogFactory()
				->newLinkRecommendationSubmissionLog( $user )
				->count()
		);
	}

	/**
	 * @param int $tasks
	 * @dataProvider provideTestCount
	 * @covers \GrowthExperiments\NewcomerTasks\NewcomerTasksLog
	 * @covers \GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationSubmissionLogFactory
	 */
	public function testCountImages( int $tasks ) {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$this->logNewcomerTasks( $tasks, $user, 'addimage' );

		$this->assertSame(
			$tasks,
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getImageRecommendationSubmissionLogFactory()
				->newImageRecommendationSubmissionLog( $user )
				->count()
		);
	}

	/**
	 * @param int $tasks
	 * @dataProvider provideTestCount
	 * @covers \GrowthExperiments\NewcomerTasks\NewcomerTasksLog
	 * @covers \GrowthExperiments\NewcomerTasks\AddSectionImage\SectionImageRecommendationSubmissionLogFactory
	 */
	public function testCountSectionImages( int $tasks ) {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$this->logNewcomerTasks( $tasks, $user, 'addsectionimage' );

		$this->assertSame(
			$tasks,
			GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getSectionImageRecommendationSubmissionLogFactory()
				->newSectionImageRecommendationSubmissionLog( $user )
				->count()
		);
	}
}
