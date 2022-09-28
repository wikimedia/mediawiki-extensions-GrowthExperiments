<?php

namespace GrowthExperiments\Maintenance;

use ActorMigration;
use DateTime;
use Exception;
use Generator;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\SelectQueryBuilder;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class RefreshUserImpactData extends Maintenance {

	/** @var ActorStore */
	private $actorStore;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var UserImpactLookup */
	private $userImpactLookup;

	/** @var UserImpactStore */
	private $userImpactStore;

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
		$this->setBatchSize( 100 );
	}

	/** @inheritDoc */
	public function execute() {
		$this->checkOptions();
		$this->initServices();
		foreach ( $this->getUsers() as $user ) {
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

	private function checkOptions(): void {
		if ( !$this->hasOption( 'editedWithin' ) && !$this->hasOption( 'registeredWithin' ) ) {
			$this->fatalError( 'must use at least one of --editedWithin and --registeredWithin' );
		}
	}

	private function initServices(): void {
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$this->actorStore = $services->getActorStore();
		$this->actorMigration = $services->getActorMigration();
		$this->userImpactLookup = $growthServices->getUncachedUserImpactLookup();
		$this->userImpactStore = $growthServices->getUserImpactStore();
	}

	/**
	 * @return Generator<UserIdentity>
	 */
	private function getUsers(): Generator {
		$ignoreIfUpdatedWithin = $this->getOption( 'ignoreIfUpdatedWithin' );

		$queryBuilder = $this->getQueryBuilder();
		$queryBuilder->fields( [ 'actor_id', 'actor_name', 'actor_user' ] );
		$queryBuilder->orderBy( 'actor_user' );
		$queryBuilder->limit( $this->getBatchSize() );
		$lastUserId = (int)$this->getOption( 'fromUser', 0 );
		do {
			$this->output( "processing {$this->getBatchSize()} users starting with $lastUserId\n" );
			$batchQueryBuilder = clone $queryBuilder;
			$batchQueryBuilder->where( 'actor_user > ' . $lastUserId );
			$usersProcessedInThisBatch = 0;
			foreach ( $batchQueryBuilder->fetchResultSet() as $row ) {
				$user = $this->actorStore->newActorFromRow( $row );
				$lastUserId = $user->getId();
				$usersProcessedInThisBatch++;
				if ( $ignoreIfUpdatedWithin ) {
					$timestamp = $this->getTimestampFromRelativeDate( $ignoreIfUpdatedWithin );
					$cachedUserImpact = $this->userImpactStore->getExpensiveUserImpact( $user );
					if ( $cachedUserImpact && $cachedUserImpact->getGeneratedAt() >= $timestamp ) {
						if ( $this->hasOption( 'verbose' ) ) {
							$this->output( "  ...skipping user {$user->getId()}, has recent cached entry\n" );
						}
						continue;
					}
				}
				yield $user;
			}
			$this->waitForReplication();
			if ( $usersProcessedInThisBatch > 0 ) {
				$this->output( "  processed $usersProcessedInThisBatch users\n" );
			}
		} while ( $usersProcessedInThisBatch === $this->getBatchSize() );
	}

	private function getQueryBuilder(): SelectQueryBuilder {
		$editedWithin = $this->getOption( 'editedWithin' );
		$registeredWithin = $this->getOption( 'registeredWithin' );

		// Can't use UserSelectQueryBuilder because it doesn't work with ActorMigration
		$queryBuilder = new SelectQueryBuilder( $this->getDB( DB_REPLICA ) );
		if ( $editedWithin ) {
			$timestamp = $this->getDB( DB_REPLICA )->timestamp(
				$this->getTimestampFromRelativeDate( $editedWithin ) );
			$queryBuilder->table( 'revision' );
			$queryInfo = $this->actorMigration->getJoin( 'rev_user' );
			$queryBuilder->tables( $queryInfo['tables'] );
			$queryBuilder->fields( $queryInfo['fields'] );
			$queryBuilder->joinConds( $queryInfo['joins'] );
			$queryBuilder->where( "rev_timestamp >= $timestamp" );
			$queryBuilder->groupBy( [ 'actor_id', 'actor_name', 'actor_user' ] );
		}
		if ( $registeredWithin ) {
			$timestamp = $this->getDB( DB_REPLICA )->timestamp(
				$this->getTimestampFromRelativeDate( $registeredWithin ) );
			if ( !$editedWithin ) {
				$queryBuilder->table( 'actor' );
			}
			$queryBuilder->join( 'user', null, [ 'actor_user = user_id' ] );
			$queryBuilder->where( "user_registration >= $timestamp" );
		}
		return $queryBuilder;
	}

	/**
	 * @param string $relativeDate A relative date string fragment that will be prefixed with a
	 *   minus sign and passed to the DateTime constr
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
