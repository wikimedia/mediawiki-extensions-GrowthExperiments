<?php

declare( strict_types = 1 );

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Json\FormatJson;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\LBFactory;

/**
 * Updates growthexperiments_mentee_data
 *
 * WARNING: This class may submit heavy queries that take minutes (or hours)
 * to complete. It may only be used from CLI scripts or MediaWiki jobs.
 */
class MenteeOverviewDataUpdater {
	public const LAST_UPDATE_PREFERENCE = 'growthexperiments-mentor-dashboard-last-update';

	private UncachedMenteeOverviewDataProvider $uncachedMenteeOverviewDataProvider;
	private MenteeOverviewDataProvider $menteeOverviewDataProvider;
	private MentorStore $mentorStore;
	private UserOptionsManager $userOptionsManager;
	private LBFactory $lbFactory;
	private ILoadBalancer $growthLoadBalancer;
	private int $batchSize = 100;
	private array $mentorProfilingInfo = [];

	public function __construct(
		UncachedMenteeOverviewDataProvider $uncachedMenteeOverviewDataProvider,
		MenteeOverviewDataProvider $menteeOverviewDataProvider,
		MentorStore $mentorStore,
		UserOptionsManager $userOptionsManager,
		LBFactory $lbFactory,
		ILoadBalancer $growthLoadBalancer
	) {
		$this->uncachedMenteeOverviewDataProvider = $uncachedMenteeOverviewDataProvider;
		$this->uncachedMenteeOverviewDataProvider->setBatchSize( $this->batchSize );
		$this->menteeOverviewDataProvider = $menteeOverviewDataProvider;
		$this->mentorStore = $mentorStore;
		$this->userOptionsManager = $userOptionsManager;
		$this->lbFactory = $lbFactory;
		$this->growthLoadBalancer = $growthLoadBalancer;
	}

	public function setBatchSize( int $batchSize ): void {
		$this->batchSize = $batchSize;
		$this->uncachedMenteeOverviewDataProvider->setBatchSize( $this->batchSize );
	}

	public function getMentorProfilingInfo(): array {
		return $this->mentorProfilingInfo;
	}

	/**
	 * @param UserIdentity $mentor
	 * @return int[] List of IDs this function updated
	 */
	public function updateDataForMentor( UserIdentity $mentor ): array {
		$this->mentorProfilingInfo = [];

		$thisBatch = 0;

		$dbw = $this->growthLoadBalancer->getConnection( DB_PRIMARY );
		$dbr = $this->growthLoadBalancer->getConnection( DB_REPLICA );

		$data = $this->uncachedMenteeOverviewDataProvider->getFormattedDataForMentor( $mentor );
		$mentees = $this->mentorStore->getMenteesByMentor( $mentor, MentorStore::ROLE_PRIMARY );
		$updatedMenteeIds = [];
		foreach ( $data as $menteeId => $menteeData ) {
			$encodedData = FormatJson::encode( $menteeData );
			$storedEncodedData = $dbr->newSelectQueryBuilder()
				->select( 'mentee_data' )
				->from( 'growthexperiments_mentee_data' )
				->where( [ 'mentee_id' => $menteeId ] )
				->caller( __METHOD__ )->fetchField();
			if ( $storedEncodedData === false ) {
				// Row doesn't exist yet, insert it
				$dbw->newInsertQueryBuilder()
					->insertInto( 'growthexperiments_mentee_data' )
					->row( [
						'mentee_id' => $menteeId,
						'mentee_data' => $encodedData
					] )
					->caller( __METHOD__ )
					->execute();
			} else {
				// Row exists, if anything changed, update
				if ( FormatJson::decode( $storedEncodedData, true ) !== $menteeData ) {
					$dbw->newUpdateQueryBuilder()
						->update( 'growthexperiments_mentee_data' )
						->set( [ 'mentee_data' => $encodedData ] )
						->where( [ 'mentee_id' => $menteeId ] )
						->caller( __METHOD__ )
						->execute();
				}
			}

			$thisBatch++;
			$updatedMenteeIds[] = $menteeId;

			if ( $thisBatch >= $this->batchSize ) {
				$thisBatch = 0;
				$this->lbFactory->waitForReplication();
				$this->lbFactory->autoReconfigure();
			}
		}

		// Delete all mentees of $mentor we did not update
		$menteeIdsToDelete = array_diff(
			array_map(
				static function ( $mentee ) {
					return $mentee->getId();
				},
				$mentees
			),
			$updatedMenteeIds
		);
		if ( $menteeIdsToDelete !== [] ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'growthexperiments_mentee_data' )
				->where( [
					'mentee_id' => $menteeIdsToDelete
				] )
				->caller( __METHOD__ )
				->execute();
			$this->lbFactory->waitForReplication();
		}

		$this->mentorProfilingInfo = $this->uncachedMenteeOverviewDataProvider
			->getProfilingInfo();

		// if applicable, clear cache for the mentor we just updated
		if ( $this->menteeOverviewDataProvider instanceof DatabaseMenteeOverviewDataProvider ) {
			$this->menteeOverviewDataProvider->invalidateCacheForMentor( $mentor );
		}

		// update the last update timestamp
		$this->userOptionsManager->setOption(
			$mentor,
			self::LAST_UPDATE_PREFERENCE,
			wfTimestamp( TS_MW )
		);
		$this->userOptionsManager->saveOptions( $mentor );

		return $updatedMenteeIds;
	}
}
