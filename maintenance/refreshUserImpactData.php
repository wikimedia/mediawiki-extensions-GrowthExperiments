<?php

namespace GrowthExperiments\Maintenance;

use DateTime;
use Exception;
use Generator;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\UserDatabaseHelper;
use GrowthExperiments\UserImpact\RefreshUserImpactJob;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserSelectQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class RefreshUserImpactData extends Maintenance {

	private ActorStore $actorStore;
	private UserFactory $userFactory;
	private UserImpactLookup $userImpactLookup;
	private UserImpactStore $userImpactStore;
	private UserDatabaseHelper $userDatabaseHelper;

	private JobQueueGroupFactory $jobQueueGroupFactory;

	/** @var int|null Ignore a user if they have data generated after this Unix timestamp. */
	private ?int $ignoreAfter = null;

	private int $totalUsers = 0;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Update data in the growthexperiments_user_impact table.' );
		$this->addOption( 'editedWithin', 'Apply to users who have edited within the given time.'
			. ' Time is a relative timestring fragment passed to DateTime, such as "30days".', false, true );
		$this->addOption( 'registeredWithin', 'Apply to users who have registered within the given time.'
			. ' Time is a relative timestring fragment passed to DateTime, such as "30days".', false, true );
		$this->addOption( 'hasEditsAtLeast', 'Apply to users who have at least this many edits.', false, true );
		$this->addOption( 'ignoreIfUpdatedWithin', 'Skip cache records which were stored within the given time.'
			. ' Time is a relative timestring fragment passed to DateTime, such as "30days".', false, true );
		$this->addOption( 'fromUser', 'Continue from the given user ID (exclusive).', false, true );
		$this->addOption( 'use-job-queue', 'If job queue should be used to refresh user impact data.' );
		$this->addOption( 'force', 'Run even if GERefreshUserImpactDataMaintenanceScriptEnabled is false' );
		$this->addOption( 'dry-run', 'When used, the script will only count the number of users it would update.' );
		$this->addOption( 'verbose', 'Verbose mode' );
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	public function execute() {
		if ( !$this->getConfig()->get( 'GERefreshUserImpactDataMaintenanceScriptEnabled' )
			&& !$this->hasOption( 'force' )
		) {
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
			$realUser = $this->userFactory->newFromUserIdentity( $user );
			if ( $realUser->isHidden() ) {
				// do not update impact data for hidden users (T337845)
				$this->output( " ...skipping user {$user->getId()}, hidden.\n" );
				continue;
			}
			if ( $realUser->isBot() ) {
				// do not update impact data for bots (T351898)
				$this->output( " ...skipping user {$user->getId()}, bot.\n" );
				continue;
			}

			if ( $this->hasOption( 'dry-run' ) ) {
				if ( $this->hasOption( 'verbose' ) ) {
					$this->output( "  ...would refresh user impact for user {$user->getId()}\n" );
				}
				continue;
			} elseif ( $this->hasOption( 'use-job-queue' ) ) {
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

		if ( $this->totalUsers ) {
			$this->output( "Done. Processed $this->totalUsers users.\n" );
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
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->actorStore = $services->getActorStore();
		$this->userFactory = $services->getUserFactory();
		$this->jobQueueGroupFactory = $services->getJobQueueGroupFactory();
		$this->userImpactLookup = $growthServices->getUncachedUserImpactLookup();
		$this->userImpactStore = $growthServices->getUserImpactStore();
		$this->userDatabaseHelper = $growthServices->getUserDatabaseHelper();
	}

	/**
	 * @return Generator<UserIdentity>
	 */
	private function getUsers(): Generator {
		$queryBuilder = $this->getQueryBuilder();
		$queryBuilder->select( 'actor_user' );
		$queryBuilder->limit( $this->getBatchSize() );
		$queryBuilder->orderByUserId( SelectQueryBuilder::SORT_ASC );
		$queryBuilder->caller( __METHOD__ );
		$lastUserId = (int)$this->getOption( 'fromUser', 0 );
		$dbr = $this->getReplicaDB();
		do {
			$this->output( "processing {$this->getBatchSize()} users starting with $lastUserId\n" );
			$batchQueryBuilder = clone $queryBuilder;
			$batchQueryBuilder->where( $dbr->expr( 'actor_user', '>', $lastUserId ) );
			$userIds = $batchQueryBuilder->fetchFieldValues();
			if ( $userIds ) {
				$users = $this->actorStore->newSelectQueryBuilder( $dbr )
					->whereUserIds( $userIds )
					->caller( __METHOD__ )
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
			$this->totalUsers += $usersProcessedInThisBatch;
			if ( $usersProcessedInThisBatch > 0 ) {
				$this->output( "  processed $usersProcessedInThisBatch users\n" );
			}
		} while ( $usersProcessedInThisBatch === $this->getBatchSize() );
	}

	private function getQueryBuilder(): UserSelectQueryBuilder {
		$editedWithin = $this->getOption( 'editedWithin' );
		$registeredWithin = $this->getOption( 'registeredWithin' );
		$hasEditsAtLeast = $this->getOption( 'hasEditsAtLeast' );

		$dbr = $this->getReplicaDB();
		$queryBuilder = $this->actorStore->newSelectQueryBuilder( $dbr );
		if ( $editedWithin ) {
			$timestamp = $dbr->timestamp( $this->getTimestampFromRelativeDate( $editedWithin ) );
			$queryBuilder->join( 'revision', null, [ 'rev_actor = actor_id' ] );
			$queryBuilder->where( $dbr->expr( 'rev_timestamp', '>=', $timestamp ) );
			$queryBuilder->groupBy( [ 'actor_user' ] );
		}
		if ( $registeredWithin ) {
			$firstUserId = $this->userDatabaseHelper->findFirstUserIdForRegistrationTimestamp(
				$this->getTimestampFromRelativeDate( $registeredWithin )
			);
			if ( $firstUserId ) {
				$queryBuilder->where( $dbr->expr( 'actor_user', '>=', $firstUserId ) );
			} else {
				$queryBuilder->where( '0 = 1' );
			}
		}
		if ( $hasEditsAtLeast ) {
			$queryBuilder->join( 'user', null, [ 'user_id = actor_user' ] );
			$queryBuilder->where( $dbr->expr( 'user_editcount', '>=', (int)$hasEditsAtLeast ) );
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
