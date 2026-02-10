<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ExperimentTestKitchenManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\TestKitchen\Sdk\Experiment;
use MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;

/**
 * @coversDefaultClass \GrowthExperiments\ExperimentTestKitchenManager
 */
class ExperimentTestKitchenManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantNoExperiment() {
		$user = new UserIdentityValue( 0, __CLASS__ );
		$options = [
			new ServiceOptions(

				ExperimentTestKitchenManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'TestKitchenEnableExperiments' => false,
					'TestKitchenEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			$this->getExperimentManager(),
			new HashConfig( [ 'TestKitchenExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentTestKitchenManager {
			// No valid experiments, fallback to configured default, same as for ExperimentUserManager
			public const VALID_EXPERIMENTS = [];
		};

		$this->assertEquals( 'Foo', $sut->getVariant( $user ) );
	}

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantNoEnrolledExperiments() {
		$user1 = new UserIdentityValue( 1, __CLASS__ );
		$options = [
			new ServiceOptions(

				ExperimentTestKitchenManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'TestKitchenEnableExperiments' => false,
					'TestKitchenEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			$this->getExperimentManager(
				[
					'experiment-1' => null,
					'experiment-2' => null,
				]
			),
			new HashConfig( [ 'TestKitchenExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentTestKitchenManager {
			public const VALID_EXPERIMENTS = [
				'experiment-2',
				'experiment-1',
			];
		};
		$this->assertEquals( "Foo", $sut->getVariant( $user1 ) );
	}

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantWithUserInExperiment() {
		$user1 = new UserIdentityValue( 1, __CLASS__ );
		$options = [
			new ServiceOptions(

				ExperimentTestKitchenManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'TestKitchenEnableExperiments' => false,
					'TestKitchenEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			$this->getExperimentManager(
				[
					'experiment-3' => null,
					'experiment-2' => 'some-group',
					'experiment-1' => null,
				]
			),
			new HashConfig( [ 'TestKitchenExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentTestKitchenManager {
			public const VALID_EXPERIMENTS = [
				'experiment-3',
				'experiment-2',
				'experiment-1',
			];
		};
		$this->assertEquals(
			"experiment-2_some-group",
			$sut->getVariant( $user1 )
		);
	}

	/**
	 * @covers ::getVariant
	 */
	public function testGetVariantWithUserInMultipleExperiment() {
		$user1 = new UserIdentityValue( 1, __CLASS__ );
		$options = [
			new ServiceOptions(

				ExperimentTestKitchenManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'TestKitchenEnableExperiments' => false,
					'TestKitchenEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			$this->getExperimentManager(
				[
					'experiment-3' => 'another-group',
					'experiment-2' => 'some-group',
					'experiment-1' => null,
				]
			),
			new HashConfig( [ 'TestKitchenExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentTestKitchenManager {
			public const VALID_EXPERIMENTS = [
				'experiment-3',
				'experiment-2',
				'experiment-1',
			];
		};
		$this->assertEquals(
			"experiment-3_another-group",
			$sut->getVariant( $user1 )
		);
	}

	/**
	 * @covers ::getValidVariants
	 */
	public function testGetValidVariants() {
		$options = [
			new ServiceOptions(

				ExperimentTestKitchenManager::CONSTRUCTOR_OPTIONS,
				[
					'GEHomepageDefaultVariant' => 'Foo',
					'TestKitchenEnableExperiments' => false,
					'TestKitchenEnableExperimentConfigsFetching' => [],
				]
			),
			new NullLogger(),
			$this->getExperimentManager( [
				'experiment-1' => null,
				'experiment-2' => null,
			] ),
			new HashConfig( [ 'TestKitchenExperiments' => [] ] ),
		];
		$sut = new class( ...$options ) extends ExperimentTestKitchenManager {
			public const VALID_EXPERIMENTS = [
				'experiment-2',
				'experiment-1',
			];
		};
		$this->assertArrayContains(
			[
				'experiment-2_' . ExperimentTestKitchenManager::VARIANT_CONTROL,
				'experiment-2_' . ExperimentTestKitchenManager::VARIANT_TREATMENT,
				'experiment-1_' . ExperimentTestKitchenManager::VARIANT_CONTROL,
				'experiment-1_' . ExperimentTestKitchenManager::VARIANT_TREATMENT,
			],
			$sut->getValidVariants()
		);
	}

	private function getExperimentManager( ?array $assignments = [] ): ExperimentManager {
		if ( !class_exists( 'MediaWiki\Extension\TestKitchen\Sdk\ExperimentManager' ) ) {
			$this->markTestSkipped( 'TestKitchen\Sdk\ExperimentManager is not available.' );
		}
		$experimentManager = $this->createMock( ExperimentManager::class );
		$experimentManager->method( 'getExperiment' )
			->willReturnOnConsecutiveCalls(
				...array_map( function ( $experimentName ) use ( $assignments ) {
					$experiment = $this->createMock( Experiment::class );
					$experiment->method( 'getAssignedGroup' )
						->willReturn( $assignments[ $experimentName ] ?? null );

					return $experiment;
				}, array_keys( $assignments ) ),
			);
		return $experimentManager;
	}
}
