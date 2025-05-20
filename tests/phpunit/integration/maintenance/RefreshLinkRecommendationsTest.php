<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Maintenance\RefreshLinkRecommendations;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;
use MockHttpTrait;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers GrowthExperiments\Maintenance\RefreshLinkRecommendations
 * @group Database
 */
class RefreshLinkRecommendationsTest extends MaintenanceBaseTestCase {
	use MockHttpTrait;

	protected function getMaintenanceClass(): string {
		return RefreshLinkRecommendations::class;
	}

	public function testIteratingThroughAllPages_storesHandoverAfterLimitReached(): void {
		$this->overrideConfigValue( 'GELinkRecommendationsRefreshByIteratingThroughAllTitles', true );
		ConvertibleTimestamp::setFakeTime( '20210101000000' );

		$fakeResponse = [
			'links' => [],
			'meta' => [],
		];

		$this->installMockHttp( json_encode( $fakeResponse ) );

		$this->getExistingTestPage();
		$this->getExistingTestPage( Title::newFromText( 'Template:NotInMainNamespace1' ) );
		// following is the page with id 3, the 2nd page in the main article namespace
		$this->getExistingTestPage();
		$this->getExistingTestPage();

		ConvertibleTimestamp::setFakeTime( null );

		$this->maintenance->loadParamsAndArgs(
			null,
			[
				'verbose' => true,
				'limit' => 2,
				'batch-size' => 2,
			],
		);

		$this->maintenance->execute();

		$services = $this->getServiceContainer();

		$mainStash = $services->getMainObjectStash();
		$lastPageIdKey = $mainStash->makeKey(
			'GrowthExperiments',
			'RefreshLinkRecommendations',
			'lastPageId'
		);

		$this->assertEquals( 3, $mainStash->get( $lastPageIdKey ) );
	}

	public function testIteratingThroughAllPages_pickupAtStoredPageId(): void {
		$this->overrideConfigValue( 'GELinkRecommendationsRefreshByIteratingThroughAllTitles', true );
		$this->getExistingTestPage();
		$this->getExistingTestPage( Title::newFromText( 'Template:NotInMainNamespace1' ) );
		$this->getExistingTestPage();
		// The above were part of the "first batch", the test should now pick up at the one that follows"
		$this->getExistingTestPage();

		$this->maintenance->loadParamsAndArgs(
			null,
			[
				'verbose' => true,
				'limit' => 2,
				'batch-size' => 2,
			],
		);
		$services = $this->getServiceContainer();

		$mainStash = $services->getMainObjectStash();
		$lastPageIdKey = $mainStash->makeKey(
			'GrowthExperiments',
			'RefreshLinkRecommendations',
			'lastPageId'
		);
		$mainStash->set( $lastPageIdKey, 3, 10, BagOStuff::WRITE_CACHE_ONLY );

		// Reset the service, because editing pages above may have populated the task types in the configuration
		// loader.
		$this->getServiceContainer()->resetServiceForTesting( 'GrowthExperimentsNewcomerTasksConfigurationLoader' );
		$this->maintenance->execute();

		$this->assertFalse(
			$mainStash->get( $lastPageIdKey ),
			'lastPageId should be cleared after run reaching the end of pages available',
		);
	}

	public function testCanSetLastPageIdInStashManually(): void {
		$this->maintenance->loadParamsAndArgs(
			null,
			[
				'setLastPageIdInStash' => 21,
			],
		);

		$this->maintenance->execute();

		$services = $this->getServiceContainer();
		$mainStash = $services->getMainObjectStash();
		$lastPageIdKey = $mainStash->makeKey(
			'GrowthExperiments',
			'RefreshLinkRecommendations',
			'lastPageId'
		);
		$this->assertEquals( 21, $mainStash->get( $lastPageIdKey ) );

		$this->assertOutputPrePostShutdown(
			<<<EXPECTED_OUTPUT
Setting lastPageId in stash: 21
Successfully set lastPageId in stash
Exiting.

EXPECTED_OUTPUT
			,
			false,
		);
	}
}
