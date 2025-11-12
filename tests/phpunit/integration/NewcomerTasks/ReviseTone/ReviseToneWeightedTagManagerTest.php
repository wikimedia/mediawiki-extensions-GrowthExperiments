<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use CirrusSearch\WeightedTagsUpdater;
use GrowthExperiments\NewcomerTasks\ReviseTone\ReviseToneWeightedTagManager;
use MediaWiki\Page\PageIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrowthExperiments\NewcomerTasks\ReviseTone\ReviseToneWeightedTagManager
 */
class ReviseToneWeightedTagManagerTest extends MediaWikiIntegrationTestCase {

	public function testDeletePageReviseToneWeightedTag(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

		$loggerMock = $this->createMock( LoggerInterface::class );
		$page = PageIdentityValue::localIdentity( 0, 0, 'Main page' );
		$weightedTagsUpdaterMock = $this->createMock( WeightedTagsUpdater::class );
		$weightedTagsUpdaterMock->expects( $this->once() )
			->method( 'resetWeightedTags' )
			->with(
				$page,
				[ 'recommendation.tone' ],
			);

		$sut = new ReviseToneWeightedTagManager(
			$weightedTagsUpdaterMock,
			$loggerMock,
		);
		$sut->deletePageReviseToneWeightedTag( $page );
	}
}
