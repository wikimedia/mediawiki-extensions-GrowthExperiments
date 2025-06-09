<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationEvalStatus;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Title\TitleValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater
 * @covers \GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore::getNumberOfExcludedTemplatesOnPage
 * @group Database
 */
class LinkRecommendationUpdaterTest extends MediaWikiIntegrationTestCase {

	public function testProcessCandidateExcludedTemplate(): void {
		ConvertibleTimestamp::setFakeTime( strtotime( '3 days ago' ) );
		$page = $this->getNonexistingTestPage();
		$pageUpdateStatus = $this->editPage(
			$page,
			"Test edit\n{{ExcludedTemplate}}",
			'Test edit summary'
		);
		$this->assertStatusGood( $pageUpdateStatus );
		$this->runDeferredUpdates();
		ConvertibleTimestamp::setFakeTime( null );

		$fakeConfigLoader = new StaticConfigurationLoader( [
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => new LinkRecommendationTaskType(
				LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
				TaskType::DIFFICULTY_EASY,
				[],
				[],
				[ new TitleValue( 10, 'ExcludedTemplate' ) ],
			)
		] );

		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $fakeConfigLoader );

		/** @var LinkRecommendationUpdater $updater */
		$updater = $this->getServiceContainer()->getService( 'GrowthExperimentsLinkRecommendationUpdater' );

		$actualProcessingStatus = $updater->processCandidate( $page );

		$this->assertStatusNotOK( $actualProcessingStatus );
		$this->assertInstanceOf( LinkRecommendationEvalStatus::class, $actualProcessingStatus );
		$this->assertSame(
			LinkRecommendationEvalStatus::NOT_GOOD_CAUSE_EXCLUDED_TEMPLATE,
			$actualProcessingStatus->getNotGoodCause()
		);
	}

	public function testProcessCandidateExcludedCategory(): void {
		ConvertibleTimestamp::setFakeTime( strtotime( '3 days ago' ) );
		$page = $this->getNonexistingTestPage();
		$pageUpdateStatus = $this->editPage(
			$page,
			"Test edit\n[[Category:ExcludedCategory]]",
			'Test edit summary'
		);
		$this->assertStatusGood( $pageUpdateStatus );
		ConvertibleTimestamp::setFakeTime( null );

		$fakeConfigLoader = new StaticConfigurationLoader( [
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => new LinkRecommendationTaskType(
				LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
				TaskType::DIFFICULTY_EASY,
				[],
				[],
				[],
				[ new TitleValue( 14, 'ExcludedCategory' ) ],
			)
		] );

		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $fakeConfigLoader );

		/** @var LinkRecommendationUpdater $updater */
		$updater = $this->getServiceContainer()->getService( 'GrowthExperimentsLinkRecommendationUpdater' );

		$actualProcessingStatus = $updater->processCandidate( $page );

		$this->assertStatusNotOK( $actualProcessingStatus );
		$this->assertInstanceOf( LinkRecommendationEvalStatus::class, $actualProcessingStatus );
		$this->assertSame(
			LinkRecommendationEvalStatus::NOT_GOOD_CAUSE_EXCLUDED_CATEGORY,
			$actualProcessingStatus->getNotGoodCause()
		);
	}

	public function testProcessCandidateRecentlyEdited(): void {
		$page = $this->getExistingTestPage();
		/** @var LinkRecommendationUpdater $updater */
		$updater = $this->getServiceContainer()->getService( 'GrowthExperimentsLinkRecommendationUpdater' );

		$actualProcessingStatus = $updater->processCandidate( $page );

		$this->assertStatusNotOK( $actualProcessingStatus );
		$this->assertInstanceOf( LinkRecommendationEvalStatus::class, $actualProcessingStatus );
		$this->assertSame(
			LinkRecommendationEvalStatus::NOT_GOOD_CAUSE_MINIMUM_TIME_DID_NOT_PASS,
			$actualProcessingStatus->getNotGoodCause()
		);
	}
}
