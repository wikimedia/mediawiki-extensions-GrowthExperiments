<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use CirrusSearch\WeightedTagsUpdater;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\Assert;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Stats\Metrics\CounterMetric;
use Wikimedia\Stats\StatsFactory;

/**
 * @group Database
 * @covers \GrowthExperiments\NewcomerTasks\MediaWikiEventSubscribers\PageUpdatedSubscriber
 */
class NewcomerTasksPageUpdatedSubscriberTest extends MediaWikiIntegrationTestCase {

	public function makeResetWeightedTagCallback( ProperPageIdentity $expectedPage, array $expectedTagPrefix ) {
		return static function ( ProperPageIdentity $page, array $tagPrefix )
			use ( $expectedPage, $expectedTagPrefix )
		{
			Assert::assertTrue( $page->isSamePageAs( $expectedPage ) );
			Assert::assertEquals( $expectedTagPrefix, $tagPrefix );
		};
	}

	public function testClearLinkRecommendationOnPageSaveComplete(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$wikiPage = $this->getExistingTestPage();
		$weightedTagsUpdaterMock = $this->createMock( WeightedTagsUpdater::class );
		$weightedTagsUpdaterMock->expects( $this->once() )
			->method( 'resetWeightedTags' )->willReturnCallback(
				$this->makeResetWeightedTagCallback(
					$wikiPage,
					[ LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX ]
				)
			);

		$this->setService( WeightedTagsUpdater::SERVICE, $weightedTagsUpdaterMock );
		$this->overrideConfigValues( [
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
		] );
		$linkRecommendation = new LinkRecommendation(
			$wikiPage->getTitle(),
			$wikiPage->getId(),
			0,
			[],
			LinkRecommendation::getMetadataFromArray( [] )
		);
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$linkRecommendationStore = $geServices->getLinkRecommendationStore();
		$linkRecommendationStore->insertExistingLinkRecommendation( $linkRecommendation );

		$this->editPage( $wikiPage, 'new content' );

		$fromPageId = 0;
		$this->assertCount( 0, $linkRecommendationStore->getAllExistingRecommendations( 100, $fromPageId ) );
	}

	public function testClearLinkRecommendationNoPrimaryWriteWithoutReplicaMatch(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$wikiPage = $this->getExistingTestPage();
		$weightedTagsUpdaterMock = $this->createMock( WeightedTagsUpdater::class );
		$weightedTagsUpdaterMock->expects( $this->once() )
			->method( 'resetWeightedTags' )->willReturnCallback(
				$this->makeResetWeightedTagCallback(
					$wikiPage,
					[ LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX ]
				)
			);

		$this->setService( WeightedTagsUpdater::SERVICE, $weightedTagsUpdaterMock );
		$this->overrideConfigValues( [
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
		] );
		$mockLinkRecommendationStore = $this->createMock( LinkRecommendationStore::class );
		$mockLinkRecommendationStore->expects( $this->once() )
			->method( 'getByPageId' )
			->with( $wikiPage->getId(), IDBAccessObject::READ_NORMAL )
			->willReturn( null );
		$mockLinkRecommendationStore->expects( $this->never() )
			->method( 'deleteByPageIds' );
		$this->setService( 'GrowthExperimentsLinkRecommendationStore', $mockLinkRecommendationStore );

		$this->editPage( $wikiPage, 'new content' );
	}

	public function testDoNotClearLinkRecommendationForNewPage(): void {
		$wikiPage = $this->getNonexistingTestPage();
		$this->overrideConfigValues( [
			'GENewcomerTasksLinkRecommendationsEnabled' => true,
		] );
		$weightedTagsUpdaterMock = $this->createMock( WeightedTagsUpdater::class );
		$weightedTagsUpdaterMock->expects( $this->never() )
			->method( 'resetWeightedTags' );
		$this->setService( WeightedTagsUpdater::SERVICE, $weightedTagsUpdaterMock );

		$this->editPage( $wikiPage, 'new content' );
	}

	public static function provideRevertScenarios(): iterable {
		yield 'Add Link tag' => [
			LinkRecommendationTaskTypeHandler::CHANGE_TAG,
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
		];
		yield 'Add Image tag' => [
			ImageRecommendationTaskTypeHandler::CHANGE_TAG,
			ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		];
		yield 'Add Section Image tag' => [
			SectionImageRecommendationTaskTypeHandler::CHANGE_TAG,
			SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
		];
		yield 'Template based task tag' => [
			'newcomer task copyedit',
			'copyedit',
		];
	}

	/**
	 * @dataProvider provideRevertScenarios
	 */
	public function testNewcomerTaskRevertIsTracked( string $tag, string $expectedTaskTypeLabel ): void {
		$user = $this->getTestUser()->getUser();
		$wikiPage = $this->getExistingTestPage();

		$invokedCount = $this->exactly( 2 );
		$wiki = WikiMap::getCurrentWikiId();
		$newcomerRevertedTaskCounterMock = $this->prepareCounterMock();
		$newcomerRevertedTaskCounterMock
			->expects( $invokedCount )
			->method( 'setLabel' )
			->willReturnCallback( function ( ...$parameters ) use (
				$invokedCount,
				$newcomerRevertedTaskCounterMock,
				$wiki,
				$expectedTaskTypeLabel
			) {
				if ( $invokedCount->getInvocationCount() === 1 ) {
					$this->assertEquals( 'taskType', $parameters[0] );
					$this->assertEquals( $expectedTaskTypeLabel, $parameters[1] );
				}

				if ( $invokedCount->getInvocationCount() === 2 ) {
					$this->assertEquals( 'wiki', $parameters[0] );
					$this->assertEquals( $wiki, $parameters[1] );
				}
				return $newcomerRevertedTaskCounterMock;
			} );
		$newcomerRevertedTaskCounterMock->method( 'copyToStatsdAt' )->willReturnSelf();
		$newcomerRevertedTaskCounterMock->expects( $this->once() )->method( 'increment' );

		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$pageUpdater->setContent( SlotRecord::MAIN, new WikitextContent( 'first revision' ) );
		$pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'first revision comment' ),
		);

		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$pageUpdater->setContent( SlotRecord::MAIN, new WikitextContent( 'newcomer edit' ) );
		$pageUpdater->addTags( [ 'newcomer task', $tag ] );
		$newcomerRevId = $pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'edit by newcomer' ),
		)->getId();

		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$pageUpdater->setContent( SlotRecord::MAIN, new WikitextContent( 'first revision' ) );
		$pageUpdater->markAsRevert( EditResult::REVERT_MANUAL, $newcomerRevId );
		$pageUpdater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'revert comment' ),
		);
	}

	private function prepareCounterMock() {
		$originalStatsFactory = $this->getServiceContainer()->getService( 'StatsFactory' );
		$statsFactoryStub = $this->createStub( StatsFactory::class );
		$growthStatsFactoryStub = $this->createStub( StatsFactory::class );
		$newcomerRevertedTaskCounterMock = $this->createMock( CounterMetric::class );

		$growthStatsFactoryStub->method( 'getCounter' )->willReturnCallback(
			static function ( $name ) use ( $originalStatsFactory, $newcomerRevertedTaskCounterMock ) {
				if ( $name === 'newcomertask_reverted_total' ) {
					return $newcomerRevertedTaskCounterMock;
				}
				return $originalStatsFactory->getCounter( $name );
			}
		);
		$growthStatsFactoryStub->method( 'getGauge' )->willReturnCallback(
			static function ( $name ) use ( $originalStatsFactory ) {
				return $originalStatsFactory->getGauge( $name );
			}
		);
		$growthStatsFactoryStub->method( 'getTiming' )->willReturnCallback(
			static function ( $name ) use ( $originalStatsFactory ) {
				return $originalStatsFactory->getTiming( $name );
			}
		);

		$statsFactoryStub->method( 'withComponent' )->willReturnCallback(
			static function ( $component ) use ( $originalStatsFactory, $growthStatsFactoryStub ) {
				if ( $component === 'GrowthExperiments' ) {
					return $growthStatsFactoryStub;
				}
				return $originalStatsFactory->withComponent( $component );
			}
		);
		$statsFactoryStub->method( 'getCounter' )->willReturnCallback(
			static function ( $name ) use ( $originalStatsFactory ) {
				return $originalStatsFactory->getCounter( $name );
			}
		);
		$statsFactoryStub->method( 'getGauge' )->willReturnCallback(
			static function ( $name ) use ( $originalStatsFactory ) {
				return $originalStatsFactory->getGauge( $name );
			}
		);
		$statsFactoryStub->method( 'getTiming' )->willReturnCallback(
			static function ( $name ) use ( $originalStatsFactory ) {
				return $originalStatsFactory->getTiming( $name );
			}
		);
		$this->setService( 'StatsFactory', $statsFactoryStub );

		return $newcomerRevertedTaskCounterMock;
	}
}
