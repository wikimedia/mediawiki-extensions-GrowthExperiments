<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ExperimentUserDefaultsManager;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \GrowthExperiments\ExperimentUserDefaultsManager
 */
class ExperimentUserDefaultsManagerTest extends MediaWikiUnitTestCase {

	// Tested with a sample of 1000 users, see @dataProvider provideShouldAssignBucket
	private const ACCEPTABLE_ERROR_PRECISION = 1.0;

	/**
	 * @covers ::shouldAssignBucket
	 * @dataProvider provideShouldAssignBucket
	 * @param UserIdentityValue[] $users The users to assign a bucket
	 * @param array $bucketConfig Array of bucket condition descriptors in the form
	 * [ BUCKET_NAME, [ CUCOND_BUCKET_BY_USER_ID, EXPERIMENT_NAME, BUCKET_PERCENTAGE ] ]
	 */
	public function testShouldAssignBucket( array $users, array $bucketConfig, array $expected ) {
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$manager = $this->getExperimentUserManager( $centralIdLookup );

		$results = [];
		foreach ( $bucketConfig as $bucket ) {
			$results[$bucket[0]] = 0;
		}
		$centralIdLookup->expects( $this->atLeastOnce() )
			->method( 'centralIdFromLocalUser' )
			->willReturnCallback( static function ( UserIdentityValue $user ) {
				return $user->getId();
			} );

		// Simulate conditional defaults execution
		foreach ( $users as $user ) {
			$ctx = [];
			foreach ( $bucketConfig as $bucket ) {
				[ $variant, $args ] = $bucket;
				if ( $manager->shouldAssignBucket( $user, $args[1], array_slice( $args, 2 ), $ctx ) ) {
					$results[ $variant ]++;
					break;
				}
			}
		}

		$sampleSize = count( $users );
		foreach ( $results as $bucketName => $bucketResult ) {
			$bucketPercentage = $expected[$bucketName];
			$bucketMsg = 'Bucket ' . $bucketName . ' has a result of ' . ( $bucketResult / $sampleSize ) * 100;
			$expectedMsg = 'Expected ' . $bucketPercentage . ' with error ' . self::ACCEPTABLE_ERROR_PRECISION;
			$this->assertSame(
				true,
				$this->assertIsAcceptablePrecision( $bucketResult, $bucketPercentage, $sampleSize ),
				$bucketMsg . '. ' . $expectedMsg . '. '
			);
		}
	}

	public static function provideShouldAssignBucket(): array {
		return [
			[
				self::getUserSample( 12, 1000 ),
				[
					[ 'bucketA', [
						ExperimentUserDefaultsManager::CUCOND_BUCKET_BY_USER_ID, 'bucketing-experiment', 50
					] ],
					[ 'bucketB', [
						ExperimentUserDefaultsManager::CUCOND_BUCKET_BY_USER_ID, 'bucketing-experiment', 75
					] ],
					[ 'bucketC', [
						ExperimentUserDefaultsManager::CUCOND_BUCKET_BY_USER_ID, 'bucketing-experiment', 100
					] ],
				],
				[
					'bucketA' => 50,
					'bucketB' => 25,
					'bucketC' => 25,
				]
			],
		];
	}

	/**
	 * Generate a sample of users to evaluate defaults. User ids are consecutive
	 * @return array|UserIdentityValue[][]
	 */
	private static function getUserSample( int $seed = 0, int $size = 10 ): array {
		return array_map( static function ( int $userId ) use ( $seed ) {
			return new UserIdentityValue( $seed + $userId, 'test user', false );
		}, range( 1, $size ) );
	}

	/**
	 * Determine if the distribution for a variant is acceptable accounting for the precision error
	 * set in ExperimentUserDefaultsManagerTest::ACCEPTABLE_ERROR_PRECISION
	 * @param int $result The number of occurrences of a result in a sample of size $sampleSize
	 * @param int $targetPercentage The goal percentage number for the result, from 0 to 100.
	 * @param int $sampleSize The number of samples
	 * @return bool Whether the result satisfies the goal with the acceptable error
	 */
	private function assertIsAcceptablePrecision( int $result, int $targetPercentage, int $sampleSize ): bool {
		$normalized = ( $result / $sampleSize ) * 100;
		if ( abs( $normalized - $targetPercentage ) > self::ACCEPTABLE_ERROR_PRECISION ) {
			return false;
		}
		return true;
	}

	private function getExperimentUserManager(
		CentralIdLookup $centralIdLookup
	): ExperimentUserDefaultsManager {
		return new ExperimentUserDefaultsManager(
			static function () use ( $centralIdLookup ) {
				return $centralIdLookup;
			}
		);
	}
}
