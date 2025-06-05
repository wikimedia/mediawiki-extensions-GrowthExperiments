<?php

namespace GrowthExperiments;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use Psr\Log\LoggerInterface;

/**
 * Service for assigning an experiment variant to users, using configured
 * variants in conditional user options.
 * @see https://www.mediawiki.org/wiki/Manual:$wgConditionalUserOptions
 */
class ExperimentUserDefaultsManager {

	public const CUCOND_BUCKET_BY_USER_ID = 'user-bucket-growth';
	public const CUCOND_BUCKET_BY_LOCAL_USER_ID = 'local-user-bucket-growth';

	private LoggerInterface $logger;

	/**
	 * CentralIdLookup must be provided as a callback function to avoid circular dependency
	 * @var callable
	 */
	private $centralIdLookupCallback;

	private UserIdentityUtils $userIdentityUtils;

	public function __construct(
		LoggerInterface $logger, callable $centralIdLookupCallback, UserIdentityUtils $userIdentityUtils
	) {
		$this->logger = $logger;
		$this->centralIdLookupCallback = $centralIdLookupCallback;
		$this->userIdentityUtils = $userIdentityUtils;
	}

	/**
	 * Decide whether a bucket should be assigned based on experiment configuration and using
	 * central user ID as the seed for computing a user sample. This has the benefit of being consistent across
	 * wikis, so assigning the same bucket. Central ID may not be available at the time of local account creation,
	 * see T380500.
	 *
	 * @param UserIdentity $userIdentity The user being evaluated
	 * @param string $experimentName The name of the experiment
	 * @param array $args An array with the condition arguments.
	 * @return bool Whether the user option being evaluated (bucket) should be returned as the default
	 */
	public function shouldAssignGlobalBucket( UserIdentity $userIdentity, string $experimentName, array $args ): bool {
		if ( !$this->canHaveBucket( $userIdentity ) ) {
			$this->logSuspiciousEvaluation( $userIdentity );
			return false;
		}
		$centralIdLookupCallback = $this->centralIdLookupCallback;
		/** @var $centralIdLookup CentralIdLookup */
		$centralIdLookup = $centralIdLookupCallback();
		$userCentralId = $centralIdLookup->centralIdFromName( $userIdentity->getName() );
		if ( $userCentralId === 0 ) {
			// CentralIdLookup is documented to return a zero on failure
			// TODO: Increase severity back to error, once it stops happening so frequently (T380271)
			$this->logger->debug( __METHOD__ . ' failed to get a central user ID', [
				'exception' => new \RuntimeException,
				'userName' => $userIdentity->getName(),
			] );
			// No point in hashing an error code
			return false;
		}
		$sample = $this->getSample( $userCentralId, $experimentName );
		return $this->isInSample( $args[0], $sample );
	}

	/** Decide whether a bucket should be assigned based on experiment configuration and using
	 * local user ID as the seed for computing a user sample. Bucket assignment with this method will
	 * return unequal buckets for the same users in different wikis.
	 *
	 * @param UserIdentity $userIdentity The user being evaluated
	 * @param string $experimentName The name of the experiment
	 * @param array $args An array with the condition arguments.
	 * @return bool Whether the user option being evaluated (bucket) should be returned as the default
	 */
	public function shouldAssignLocalBucket( UserIdentity $userIdentity, string $experimentName, array $args ): bool {
		if ( !$this->canHaveBucket( $userIdentity ) ) {
			$this->logSuspiciousEvaluation( $userIdentity );
			return false;
		}
		$userId = $userIdentity->getId();
		if ( $userId === 0 ) {
			// No point in hashing an error code
			return false;
		}
		$sample = $this->getSample( $userId, $experimentName );
		return $this->isInSample( $args[0], $sample );
	}

	/**
	 * Decide whether a user can have a bucket. Used to
	 * avoid assigning variant for anon and temporary users, see T380294.
	 */
	private function canHaveBucket( UserIdentity $user ): bool {
		return $this->userIdentityUtils->isNamed( $user );
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

	private function logSuspiciousEvaluation( UserIdentity $user ): void {
		LoggerFactory::getInstance( 'GrowthExperiments' )
			->debug( 'Suspicious evaluation of unnamed user in ExperimentsHooks closure', [
				'exception' => new \RuntimeException,
				'userName' => $user->getName()
			] );
	}

}
