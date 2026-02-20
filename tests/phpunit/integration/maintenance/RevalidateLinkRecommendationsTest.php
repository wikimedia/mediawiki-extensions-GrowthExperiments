<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use CirrusSearch\WeightedTagsUpdater;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Maintenance\RevalidateLinkRecommendations;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \GrowthExperiments\Maintenance\RevalidateLinkRecommendations
 * @group Database
 */
class RevalidateLinkRecommendationsTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass(): string {
		return RevalidateLinkRecommendations::class;
	}

	protected function setUp(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		parent::setUp();
	}

	public static function provideScenarios(): iterable {
		yield 'only existing recommendations' => [
			[],
			1,
		];
		yield 'existing recommendations and unavailable recommendations' => [
			[
				'deleteNullRecommendations' => true,
			],
			0,
		];
	}

	/**
	 * @dataProvider provideScenarios
	 */
	public function testDeleteInvalidLinkRecsFromCirrusAndDatabase(
		array $additionalOptions,
		int $expectedCountInDbAfterRevalidate
	): void {
		$wikiPage = $this->getExistingTestPage();
		$wikiPageWithNoRecommendation = $this->getExistingTestPage();
		$weightedTagsUpdaterMock = $this->createMock( WeightedTagsUpdater::class );
		$weightedTagsUpdaterMock->expects( $this->once() )
			->method( 'resetWeightedTags' )
			->with(
				$wikiPage->getTitle()->toPageIdentity(),
				[ LinkRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX ]
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
		$linkRecommendationStore->insertNoLinkRecommendationFound(
			$wikiPageWithNoRecommendation->getId(),
			$wikiPageWithNoRecommendation->getLatest(),
		);

		$this->maintenance->loadParamsAndArgs(
			null,
			array_merge(
				[
					'all' => true,
					'verbose' => true,
				],
				$additionalOptions,
			)
		);

		$this->maintenance->execute();

		$limit = 10;
		$fromPageId = 0;
		$allRecs = $linkRecommendationStore->getAllRecommendationEntries( $limit, $fromPageId );
		$this->assertCount( $expectedCountInDbAfterRevalidate, $allRecs );
	}

}
