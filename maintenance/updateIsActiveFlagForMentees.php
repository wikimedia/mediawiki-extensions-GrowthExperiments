<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthConnectionProvider;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Psr\Log\LoggerInterface;
use Wikimedia\Timestamp\TimestampFormat;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class UpdateIsActiveFlagForMentees extends Maintenance {

	private UserIdentityLookup $userIdentityLookup;
	private UserRegistrationLookup $userRegistrationLookup;
	private GrowthConnectionProvider $growthConnectionProvider;
	private MentorStore $mentorStore;
	private LoggerInterface $logger;

	public function __construct() {
		parent::__construct();
		$this->setBatchSize( 200 );
		$this->requireExtension( 'GrowthExperiments' );

		$this->addDescription(
			'Set gemm_mentee_is_active to false for users who are inactive for longer' .
			'than $wgRCMaxAge.'
		);
	}

	/**
	 * Init MediaWiki services
	 */
	private function initServices(): void {
		$services = $this->getServiceContainer();
		$geServices = GrowthExperimentsServices::wrap( $services );

		$this->userIdentityLookup = $services->getUserIdentityLookup();
		$this->userRegistrationLookup = $services->getUserRegistrationLookup();
		$this->growthConnectionProvider = $geServices->getGrowthConnectionProvider();
		$this->mentorStore = $geServices->getMentorStore();
		$this->logger = $geServices->getLogger();
	}

	/**
	 * @param int[] $userIds
	 * @return array<int, string> Timestamps keyed by user ID
	 */
	private function getLastEditsBatched( array $userIds ): array {
		// TODO: This should be upstreamed, ideally...
		$result = $this->getReplicaDB()->newSelectQueryBuilder()
			->select( [ 'actor_user', 'last_edit' => 'MAX(rev_timestamp)' ] )
			->from( 'revision' )
			->join( 'actor', conds: [ 'actor_id=rev_actor' ] )
			->where( [ 'actor_user' => $userIds ] )
			->groupBy( 'actor_user' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$output = [];
		foreach ( $result as $row ) {
			$output[$row->actor_user] = $row->last_edit;
		}
		return $output;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();

		$dbr = $this->growthConnectionProvider->getReplicaDatabase();
		$menteeIds = $dbr->newSelectQueryBuilder()
			->select( 'gemm_mentee_id' )
			->from( 'growthexperiments_mentor_mentee' )
			->where( [
				'gemm_mentor_role' => MentorStore::ROLE_PRIMARY,
				'gemm_mentee_is_active' => true,
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		$menteeIds = array_map( 'intval', $menteeIds );

		foreach ( $this->newBatchIterator( $menteeIds ) as $menteeBatch ) {
			// Convert iterator to array, as we need to traverse it multiple times, which would
			// not work otherwise.
			$menteeUsers = iterator_to_array( $this->userIdentityLookup->newSelectQueryBuilder()
				->whereUserIds( $menteeBatch )
				->fetchUserIdentities() );
			$latestEdits = $this->getLastEditsBatched( $menteeBatch );
			$registrations = $this->userRegistrationLookup->getRegistrationBatch( $menteeUsers );

			$this->beginTransactionRound( __METHOD__ );
			foreach ( $menteeUsers as $menteeUser ) {
				$lastActivityTimestamp = $latestEdits[$menteeUser->getId()] ?? null;
				if ( $lastActivityTimestamp === null ) {
					$lastActivityTimestamp = $registrations[$menteeUser->getId()] ?? null;
				}

				$timeDelta = (int)wfTimestamp() - (int)wfTimestamp(
					TimestampFormat::UNIX,
					$lastActivityTimestamp
				);

				if (
					$lastActivityTimestamp === null ||
					$timeDelta > (int)$this->getConfig()->get( 'RCMaxAge' )
				) {
					$this->mentorStore->markMenteeAsInactive( $menteeUser );
				}
			}

			// Drop rows for non-existing users
			// This is here, because growthexperiments_mentor_mentee writes and main DB writes
			// are on different DB servers, and multi-DB transactions do not exist. More detailed
			// explanation is at T323128 and T434522.
			$idsToDrop = array_diff(
				$menteeBatch,
				array_map( static fn ( UserIdentity $user ) => $user->getId(), $menteeUsers )
			);
			foreach ( $idsToDrop as $idToDrop ) {
				$this->output(
					"Deleting mentor/mentee relationship for $idToDrop, user identity not found.\n"
				);
				$this->logger->warning(
					__CLASS__ . ' encountered an invalid row in growthexperiments_mentor_mentee',
					[ 'menteeId' => $idToDrop ],
				);
				$this->mentorStore->dropMenteeRelationship(
					// user does not exist; MentorStore only makes use of the user ID,
					// so construct UserIdentity manually for easier deletion.
					new UserIdentityValue( $idToDrop, 'Mentee' )
				);
			}
			$this->commitTransactionRound( __METHOD__ );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = UpdateIsActiveFlagForMentees::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
