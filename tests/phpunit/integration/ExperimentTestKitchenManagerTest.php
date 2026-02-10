<?php

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\ExperimentTestKitchenManager;
use GrowthExperiments\GrowthExperimentsServices;
use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \GrowthExperiments\ExperimentTestKitchenManager
 */
class ExperimentTestKitchenManagerTest extends MediaWikiIntegrationTestCase {
	public function testEnrollment() {
		$this->markTestSkippedIfExtensionNotLoaded( 'TestKitchen' );
		$this->overrideMwServices( null, [
			'CentralIdLookup' => function (): CentralIdLookup {
				$centralIdMock = $this->createMock( CentralIdLookup::class );
				$centralIdMock->method( 'centralIdFromName' )->willReturn( 123 );
				return $centralIdMock;
			},
			'TestKitchen.ExperimentManager' => function (): ExperimentManager {
				$experimentManagerMock = $this->createMock( ExperimentManager::class );
				$experiment = $this->createMock( Experiment::class );
				$experiment->method( 'getAssignedGroup' )->willReturn( 'control' );
				$experimentManagerMock->method( 'getExperiment' )->willReturn( $experiment );
				return $experimentManagerMock;
			},
		] );
		$this->overrideConfigValue( 'GEUseTestKitchenExtension', true );
		// At least one of the experiments needs to be a valid experiment
		// in ExperimentTestKitchenManager::VALID_EXPERIMENTS
		$this->overrideConfigValue( 'TestKitchenExperiments', [
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
		$this->assertInstanceOf( ExperimentTestKitchenManager::class, $experimentUserManager );
		$currentExperiment = $experimentUserManager->getCurrentExperiment();
		$this->assertInstanceOf( Experiment::class, $currentExperiment );
	}

}
