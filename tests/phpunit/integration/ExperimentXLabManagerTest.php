<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\ExperimentXLabManager;
use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MetricsPlatform\XLab\Experiment;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \GrowthExperiments\ExperimentXLabManager
 */
class ExperimentXLabManagerTest extends MediaWikiIntegrationTestCase {
	public function testEnrollment() {
		$this->markTestSkippedIfExtensionNotLoaded( 'MetricsPlatform' );
		$this->overrideMwServices( null, [
			'CentralIdLookup' => function (): CentralIdLookup {
				$centralIdMock = $this->createMock( CentralIdLookup::class );
				$centralIdMock->method( 'centralIdFromName' )->willReturn( 123 );
				return $centralIdMock;
			},
		] );
		$this->overrideConfigValue( 'GEUseMetricsPlatformExtension', true );
		// At least one of the experiments needs to be a valid experiment in ExperimentXLabManager::VALID_EXPERIMENTS
		$this->overrideConfigValue( 'MetricsPlatformExperiments', [
			[
				'name' => 'growthexperiments-another-made-up',
				'groups' => [
					'control',
					'treatment',
				],
				'sample' => [
					'rate' => 0.5,
				],
			],
			[
				'name' => 'growthexperiments-revise-tone',
				'groups' => [
					'control',
					'treatment',
				],
				'sample' => [
					'rate' => 0.5,
				],
			],
		] );
		$growthServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$experimentUserManager = $growthServices->getExperimentUserManager();
		$testUser = $this->getTestUser()->getUser();
		$this->assertInstanceOf( ExperimentXLabManager::class, $experimentUserManager );
		$experimentUserManager->enrollUser( RequestContext::getMain(), $testUser );
		$currentExperiment = $experimentUserManager->getCurrentExperiment();
		$this->assertInstanceOf( Experiment::class, $currentExperiment );
	}

}
