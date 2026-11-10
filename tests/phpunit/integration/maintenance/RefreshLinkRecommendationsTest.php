<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\Maintenance\RefreshLinkRecommendations;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;
use MockHttpTrait;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \GrowthExperiments\Maintenance\RefreshLinkRecommendations
 * @group Database
 */
class RefreshLinkRecommendationsTest extends MaintenanceBaseTestCase {
	use MockHttpTrait;

	protected function getMaintenanceClass(): string {
		return RefreshLinkRecommendations::class;
	}

	public function testIteratingThroughAllPages_storesHandoverAfterLimitReached(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
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

		$services = $this->getServiceContainer();
		$services->resetServiceForTesting( 'GrowthExperimentsNewcomerTasksConfigurationLoader' );
		$this->maintenance->execute();

		$mainStash = $services->getMainObjectStash();
		$lastPageIdKey = $mainStash->makeKey(
			'GrowthExperiments',
			'RefreshLinkRecommendations',
			'lastPageId'
		);

		$this->assertEquals( 3, $mainStash->get( $lastPageIdKey ) );
	}

	public function testIteratingThroughAllPages_pickupAtStoredPageId(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

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

	/**
	 * @return string The regex-quoted prefix of the output line for the page
	 */
	private function runOnOnePage( array $serviceResponse, array $options ): string {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$this->overrideConfigValue( 'GELinkRecommendationServiceUrl', 'https://linkrecommendation.test' );
		$this->installMockHttp( json_encode( $serviceResponse ) );

		ConvertibleTimestamp::setFakeTime( '20210101000000' );
		$page = $this->getExistingTestPage();
		ConvertibleTimestamp::setFakeTime( null );

		$this->maintenance->loadParamsAndArgs( null, $options );
		$this->getServiceContainer()->resetServiceForTesting( 'GrowthExperimentsNewcomerTasksConfigurationLoader' );
		$this->maintenance->execute();

		$pageName = $page->getTitle()->toPageIdentity()->__toString();
		return preg_quote( "    checking candidate $pageName... ", '/' );
	}

	public function testVerbosePrintsRoutineSkipToStdoutOnOneLine(): void {
		$this->maintenance = $this->createMaintenanceWithMarkedErrors();
		$prefix = $this->runOnOnePage( [ 'links' => [], 'meta' => [] ], [ 'verbose' => true ] );

		$this->expectOutputRegex(
			'/^' . $prefix . 'All of the links in the recommendation have been pruned$/m'
		);
	}

	public function testDefaultModeHidesRoutineSkip(): void {
		$this->maintenance = $this->createMaintenanceWithMarkedErrors();
		$this->runOnOnePage( [ 'links' => [], 'meta' => [] ], [] );

		$this->expectOutputRegex( '/^(?!.*have been pruned).*$/s' );
	}

	public function testDefaultModePrintsErrorToStderrOnOneLine(): void {
		$this->maintenance = $this->createMaintenanceWithMarkedErrors();
		$prefix = $this->runOnOnePage( [ 'error' => 'Test service failure' ], [] );

		$this->expectOutputRegex( '/^STDERR:' . $prefix . 'error: .*Test service failure/m' );
	}

	/**
	 * Under PHPUnit, Maintenance::error() prints to stdout. Mark its output to make the stream visible.
	 */
	private function createMaintenanceWithMarkedErrors(): TestingAccessWrapper {
		$maintenance = TestingAccessWrapper::newFromObject( new class extends RefreshLinkRecommendations {
			/** @inheritDoc */
			protected function error( $err ) {
				parent::error( 'STDERR:' . $err );
			}
		} );
		$maintenance->isTesting = true;
		return $maintenance;
	}

	public function testCanSetLastPageIdInStashManually(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );

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

		$this->expectOutputString(
			"Setting lastPageId in stash: 21\n" .
			"Successfully set lastPageId in stash\n" .
			"Exiting.\n"
		);
	}
}
