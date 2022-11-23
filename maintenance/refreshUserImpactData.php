<?php

namespace GrowthExperiments\Maintenance;

use DateTime;
use Exception;
use Generator;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\UserImpact\RefreshUserImpactJob;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use GrowthExperiments\UserRegistrationLookupHelper;
use Maintenance;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserSelectQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class RefreshUserImpactData extends Maintenance {

	/** @var ActorStore */
	private $actorStore;

	/** @var UserImpactLookup */
	private $userImpactLookup;

	/** @var UserImpactStore */
	private $userImpactStore;
	private JobQueueGroupFactory $jobQueueGroupFactory;

	/** @var int|null Ignore a user if they have data generated after this Unix timestamp. */
	private ?int $ignoreAfter = null;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Update data in the growthexperiments_user_impact table.' );
		$this->addOption( 'editedWithin', 'Apply to users who have edited within the given time.'
			. ' Time is a relative timestring fragment passed to DateTime, such as "30days".', false, true );
		$this->addOption( 'registeredWithin', 'Apply to users who have registered within the given time.'
			. ' Time is a relative timestring fragment passed to DateTime, such as "30days".', false, true );
		$this->addOption( 'ignoreIfUpdatedWithin', 'Skip cache records which were stored within the given time.'
			. ' Time is a relative timestring fragment passed to DateTime, such as "30days".', false, true );
		$this->addOption( 'fromUser', 'Continue from the given user ID (exclusive).', false, true );
		$this->addOption( 'verbose', 'Verbose mode' );
		$this->addOption( 'use-job-queue', 'If job queue should be used to refresh user impact data.' );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	public function execute() {
		if ( !$this->getConfig()->get( 'GERefreshUserImpactDataMaintenanceScriptEnabled' ) ) {
			$this->output(
				'GERefreshUserImpactDataMaintenanceScriptEnabled is set to false on this wiki.' .
				PHP_EOL
			);
			return;
		}
		$this->initOptions();
		$this->initServices();

		$users = [];
		foreach ( $this->getUsers() as $user ) {
			if ( $this->hasOption( 'use-job-queue' ) ) {
				$users[$user->getId()] = null;
				if ( count( $users ) >= $this->getBatchSize() ) {
					if ( $this->hasOption( 'verbose' ) ) {
						$usersText = implode( ', ', array_keys( $users ) );
						$this->output( " ... enqueueing refreshUserImpactJob for users $usersText\n" );
					}
					$this->jobQueueGroupFactory->makeJobQueueGroup()->lazyPush(
						new RefreshUserImpactJob( [
							'impactDataBatch' => $users,
							'staleBefore' => $this->ignoreAfter,
						] )
					);
					$users = [];
				}
			} else {
				$userImpact = $this->userImpactLookup->getExpensiveUserImpact( $user );
				if ( $userImpact ) {
					if ( $this->hasOption( 'verbose' ) ) {
						$this->output( "  ...refreshing user impact for user {$user->getId()}\n" );
					}
					$this->userImpactStore->setUserImpact( $userImpact );
				} elseif ( $this->hasOption( 'verbose' ) ) {
					$this->output( "  ...could not generate user impact for user {$user->getId()}\n" );
				}
			}
		}
	}

	private function initOptions(): void {
		if ( !$this->hasOption( 'editedWithin' ) && !$this->hasOption( 'registeredWithin' ) ) {
			$this->fatalError( 'must use at least one of --editedWithin and --registeredWithin' );
		}

		$ignoreIfUpdatedWithin = $this->getOption( 'ignoreIfUpdatedWithin' );
		if ( $ignoreIfUpdatedWithin ) {
			$this->ignoreAfter = $this->getTimestampFromRelativeDate( $ignoreIfUpdatedWithin );
		}
	}

	private function initServices(): void {
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->actorStore = $services->getActorStore();
		$this->jobQueueGroupFactory = $services->getJobQueueGroupFactory();
		$this->userImpactLookup = $growthServices->getUncachedUserImpactLookup();
		$this->userImpactStore = $growthServices->getUserImpactStore();
	}

	/**
	 * @return Generator<UserIdentity>
	 */
	private function getUsers(): Generator {
		$queryBuilder = $this->getQueryBuilder();
		$queryBuilder->select( 'actor_user' );
		$queryBuilder->limit( $this->getBatchSize() );
		$queryBuilder->orderByUserId( SelectQueryBuilder::SORT_ASC );
		$lastUserId = (int)$this->getOption( 'fromUser', 0 );
		do {
			$this->output( "processing {$this->getBatchSize()} users starting with $lastUserId\n" );
			$batchQueryBuilder = clone $queryBuilder;
			$batchQueryBuilder->where( 'actor_user > ' . $lastUserId );
			$userIds = $batchQueryBuilder->fetchFieldValues();
			if ( $userIds ) {
				$users = $this->actorStore->newSelectQueryBuilder( $this->getDB( DB_REPLICA ) )
					->whereUserIds( $userIds )
					->fetchUserIdentities();
			} else {
				$users = [];
			}
			foreach ( $users as $user ) {
				$lastUserId = $user->getId();
				// Do staleness check, if we are not using the job queue. Jobs can run after
				// significant delays and multiple updates for the same user might get queued,
				// so we do the check when the job runs.
				if ( $this->ignoreAfter && !$this->hasOption( 'use-job-queue' ) ) {
					$cachedUserImpact = $this->userImpactStore->getExpensiveUserImpact( $user );
					if ( $cachedUserImpact && $cachedUserImpact->getGeneratedAt() >= $this->ignoreAfter ) {
						if ( $this->hasOption( 'verbose' ) ) {
							$this->output( "  ...skipping user {$user->getId()}, has recent cached entry\n" );
						}
						continue;
					}
				}
				yield $user;
			}
			$this->waitForReplication();
			$usersProcessedInThisBatch = count( $userIds );
			if ( $usersProcessedInThisBatch > 0 ) {
				$this->output( "  processed $usersProcessedInThisBatch users\n" );
			}
		} while ( $usersProcessedInThisBatch === $this->getBatchSize() );
	}

	private function getQueryBuilder(): UserSelectQueryBuilder {
		$editedWithin = $this->getOption( 'editedWithin' );
		$registeredWithin = $this->getOption( 'registeredWithin' );

		$dbr = $this->getDB( DB_REPLICA );
		$queryBuilder = $this->actorStore->newSelectQueryBuilder( $dbr );
		if ( $editedWithin ) {
			$timestamp = $dbr->timestamp( $this->getTimestampFromRelativeDate( $editedWithin ) );
			$queryBuilder->join( 'revision', null, [ 'rev_actor = actor_id' ] );
			$queryBuilder->where( "rev_timestamp >= $timestamp" );
			$queryBuilder->groupBy( [ 'actor_user' ] );
		}
		if ( $registeredWithin ) {
			$firstUserId = UserRegistrationLookupHelper::findFirstUserIdForRegistrationTimestamp(
				$dbr,
				$this->getTimestampFromRelativeDate( $registeredWithin )
			);
			if ( $firstUserId ) {
				$queryBuilder->where( "actor_user >= $firstUserId" );
			} else {
				$queryBuilder->where( '0 = 1' );
			}
		}
		return $queryBuilder;
	}

	/**
	 * @param string $relativeDate A relative date string fragment that will be prefixed with a
	 *   minus sign and passed to the DateTime constructor.
	 * @return int
	 */
	private function getTimestampFromRelativeDate( string $relativeDate ): int {
		try {
			$dateTime = new DateTime( 'now - ' . $relativeDate );
		} catch ( Exception $e ) {
			$this->fatalError( $e->getMessage() );
		}
		return $dateTime->getTimestamp();
	}

}

$maintClass = RefreshUserImpactData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
