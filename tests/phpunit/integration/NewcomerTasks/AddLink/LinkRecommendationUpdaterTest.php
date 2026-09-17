<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationEvalStatus;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationLink;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationMetadata;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\TitleValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationUpdater
 * @covers \GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore::getNumberOfExcludedTemplatesOnPage
 * @covers \GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore::hasSubmissionOnPage
 * @group Database
 */
class LinkRecommendationUpdaterTest extends MediaWikiIntegrationTestCase {

	public function testProcessCandidateExcludedTemplate(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

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
			),
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
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

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
			),
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

	public function testProcessCandidateHasPriorSubmission(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$linkTargetPage = $this->getExistingTestPage( 'AddLinkTarget' );

		ConvertibleTimestamp::setFakeTime( strtotime( '3 days ago' ) );
		$page = $this->getNonexistingTestPage();
		$reviewedPageUpdateStatus = $this->editPage( $page, 'Test edit', 'Test edit summary' );
		$this->assertStatusGood( $reviewedPageUpdateStatus );
		/** @var RevisionRecord $reviewedRevision */
		$reviewedRevision = $reviewedPageUpdateStatus->getValue()['revision-record'];
		// A later edit makes sure the check does not only look at the last revision.
		$this->assertStatusGood( $this->editPage( $page, 'Test edit, take two', 'Test edit summary' ) );
		ConvertibleTimestamp::setFakeTime( null );

		$fakeConfigLoader = new StaticConfigurationLoader( [
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => new LinkRecommendationTaskType(
				LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
				TaskType::DIFFICULTY_EASY,
			),
		] );

		$this->setService( 'GrowthExperimentsNewcomerTasksConfigurationLoader', $fakeConfigLoader );

		$linkRecommendationStore = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
			->getLinkRecommendationStore();
		$this->assertFalse( $linkRecommendationStore->hasSubmissionOnPage( $page ) );

		// A user accepted a recommendation for the first revision of the page.
		$linkRecommendationStore->recordSubmission(
			$this->getTestUser()->getUserIdentity(),
			new LinkRecommendation(
				TitleValue::newFromPage( $page ),
				$page->getId(),
				$reviewedRevision->getId(),
				[ new LinkRecommendationLink(
					'Test', $linkTargetPage->getDBkey(), 0, 0, 1.0, '', ' edit', 0
				) ],
				new LinkRecommendationMetadata( 'v1', 1, [], 1577865600 )
			),
			[ $linkTargetPage->getId() ],
			[],
			[],
			$reviewedRevision->getId()
		);
		$this->assertTrue( $linkRecommendationStore->hasSubmissionOnPage( $page ) );

		/** @var LinkRecommendationUpdater $updater */
		$updater = $this->getServiceContainer()->getService( 'GrowthExperimentsLinkRecommendationUpdater' );

		$actualProcessingStatus = $updater->processCandidate( $page );

		$this->assertStatusNotOK( $actualProcessingStatus );
		$this->assertInstanceOf( LinkRecommendationEvalStatus::class, $actualProcessingStatus );
		$this->assertSame(
			LinkRecommendationEvalStatus::NOT_GOOD_CAUSE_HAS_PRIOR_SUBMISSION,
			$actualProcessingStatus->getNotGoodCause()
		);
	}

	public function testProcessCandidateRecentlyEdited(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

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
