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
 * @covers GrowthExperiments\Maintenance\RevalidateLinkRecommendations
 * @group Database
 */
class RevalidateLinkRecommendationsTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass(): string {
		return RevalidateLinkRecommendations::class;
	}

	public function testDeleteInvalidLinkRecsFromCirrusAndDatabase(): void {
		$wikiPage = $this->getExistingTestPage();
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

		$this->maintenance->loadParamsAndArgs(
			null,
			[
				'all' => true,
				'verbose' => true
			],
		);

		$this->maintenance->execute();

		$limit = 10;
		$fromPageId = 0;
		$allRecs = $linkRecommendationStore->getAllRecommendations( $limit, $fromPageId );
		$this->assertCount( 0, $allRecs );
	}

}
