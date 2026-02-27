<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ExperimentTestKitchenManager;
use GrowthExperiments\IExperimentManager;
use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\ExperimentTestKitchenManager
 */
class ExperimentTestKitchenManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getAssignedGroup
	 */
	public function testGetVariantNoExperiment() {
		$sut = new ExperimentTestKitchenManager( $this->getExperimentManager() );
		$this->assertNull( $sut->getAssignedGroup( 'some-experiment' ) );
	}

	/**
	 * @covers ::getAssignedGroup
	 */
	public function testGetVariantNoEnrolledExperiments() {
		$sut = new ExperimentTestKitchenManager(
			$this->getExperimentManager(
				[
					'experiment-1' => null,
					'experiment-2' => null,
				]
			)
		);
		$this->assertNull( $sut->getAssignedGroup( 'experiment-1' ) );
		$this->assertNull( $sut->getAssignedGroup( 'experiment-2' ) );
	}

	/**
	 * @covers ::getAssignments
	 */
	public function testGetAssignments() {
		$sut = new ExperimentTestKitchenManager(
			$this->getExperimentManager(
				[
					IExperimentManager::REVISE_TONE_EXPERIMENT => IExperimentManager::VARIANT_CONTROL,
					'experiment-not-recognized' => 'some-group',
				]
			)
		);
		$this->assertEquals(
			[
				IExperimentManager::REVISE_TONE_EXPERIMENT => IExperimentManager::VARIANT_CONTROL,
			],
			$sut->getAssignments()
		);
	}

	/**
	 * @covers ::getAssignedGroup
	 */
	public function testGetVariantWithUserInMultipleExperiment() {
		$sut = new ExperimentTestKitchenManager(
			$this->getExperimentManager(
				[
					'experiment-3' => 'another-group',
					'experiment-2' => 'some-group',
					'experiment-1' => null,
				]
			)
		);
		$this->assertEquals( 'another-group', $sut->getAssignedGroup( 'experiment-3' ) );
		$this->assertEquals( 'some-group', $sut->getAssignedGroup( 'experiment-2' ) );
		$this->assertNull( $sut->getAssignedGroup( 'experiment-1' ) );
	}

	private function getExperimentManager( ?array $assignments = [] ): ExperimentManager {
		if ( !class_exists( 'MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager' ) ) {
			$this->markTestSkipped( 'TestKitchen\Sdk\ExperimentManager is not available.' );
		}
		$experimentManager = $this->createMock( ExperimentManager::class );
		if ( empty( $assignments ) ) {
			$experiment = $this->createMock( Experiment::class );
			$experiment->method( 'getAssignedGroup' )
				->willReturn( null );
			$experimentManager->method( 'getExperiment' )
				->willReturn( $experiment );

		} else {
			$experimentManager->method( 'getExperiment' )
				->willReturnOnConsecutiveCalls(
					...array_map( function ( $experimentName ) use ( $assignments ) {
						$experiment = $this->createMock( Experiment::class );
						$experiment->method( 'getAssignedGroup' )
							->willReturn( $assignments[ $experimentName ] ?? null );

						return $experiment;
					}, array_keys( $assignments ) ),
				);
		}
		return $experimentManager;
	}
}
