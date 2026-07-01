<?php

namespace GrowthExperiments\Tests\Integration;

use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \GrowthExperiments\ExperimentTestKitchenManager
 */
class ExperimentTestKitchenManagerTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'TestKitchen' );
		$this->overrideMwServices( null, [
			'TestKitchen.ExperimentManager' => function (): ExperimentManager {
				$experimentManagerMock = $this->createMock( ExperimentManager::class );
				$experiment = $this->createMock( Experiment::class );
				$experiment->method( 'getAssignedGroup' )->willReturn( 'control' );
				$experimentManagerMock->method( 'getExperiment' )->willReturn( $experiment );
				return $experimentManagerMock;
			},
		] );
		// At least one of the experiments needs to be a valid experiment
		// in IExperimentManager::EXPERIMENTS
		$this->overrideConfigValue( 'TestKitchenExperiments', [
			[
				'name' => 'not-a-growthexperiments-experiment',
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
	}

}
