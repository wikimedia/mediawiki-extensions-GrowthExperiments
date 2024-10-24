<?php

namespace GrowthExperiments;

use MediaWiki\User\UserIdentity;

/**
 * Service for assigning experiment / variant to users using configured
 * variants in conditional user options.
 * @see https://www.mediawiki.org/wiki/Manual:$wgConditionalUserOptions
 */
class ExperimentUserDefaultsManager {

	public const CUCOND_BUCKET_BY_USER_ID = 'user-bucket-growth';
	/**
	 * CentralIdLookup must be provided as a callback function to avoid circular dependency
	 * @var callable
	 */
	private $centralIdLookupCallback;

	public function __construct( callable $centralIdLookupCallback ) {
		$this->centralIdLookupCallback = $centralIdLookupCallback;
	}

	/**
	 * @param UserIdentity $userIdentity The user being evaluated
	 * @param string $experimentName The name of the experiment
	 * @param array $args An array with the condition arguments.
	 * @return bool Whether the user option being evaluated (bucket) should be returned as the default
	 */
	public function shouldAssignBucket( UserIdentity $userIdentity, string $experimentName, array $args ): bool {
		$centralIdLookupCallback = $this->centralIdLookupCallback;
		/** @var CentralIdLookup */
		$centralIdLookup = $centralIdLookupCallback();
		$userCentralId = $centralIdLookup->centralIdFromLocalUser( $userIdentity );
		$sample = $this->getSample( $userCentralId, $experimentName );
		if ( $this->isInSample( $args[0], $sample ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param int $bucketRatio The probability rate for the bucket to be assigned
	 * @param int $sample The sample to test against
	 * @return bool Whether the sample is within the bucket ratio
	 */
	private function isInSample( int $bucketRatio, int $sample ): bool {
		return $sample < $bucketRatio;
	}

	private function getSample( int $userCentralId, string $experimentName ): int {
		$floatHash = $this->getUserHash( $userCentralId, $experimentName );
		return (int)( fmod( $floatHash, 1 ) * 100 );
	}

	/**
	 * Get hash of a user ID as a float between 0.0 (inclusive) and 1.0 (non-inclusive)
	 * concatenated with an experiment name.
	 *
	 * Originally taken from MediaWiki\Extension\MetricsPlatform\UserSplitter\UserSplitterInstrumentation.
	 *
	 * @param int $userId The user's id to hash
	 * @param string $experimentName The name of the experiment the bucketing applies
	 * @return float Float between 0.0 (inclusive) and 1.0 (non-inclusive) representing a user's hash
	 */
	private function getUserHash( int $userId, string $experimentName ): float {
		$userIdExperimentName = $userId . $experimentName;
		return intval( substr( md5( $userIdExperimentName ), 0, 6 ), 16 ) / ( 0xffffff + 1 );
	}

}
