<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use FormatJson;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\UserIdentity;
use stdClass;
use WANObjectCache;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Data provider for MenteeOverview module
 *
 * This data provider loads data from growthexperiments_mentee_data database
 * table and caches them for a while.
 *
 * The table is populated with data from UncachedMenteeOverviewDataProvider, see
 * that class for details about generating the data.
 */
class DatabaseMenteeOverviewDataProvider implements MenteeOverviewDataProvider, ExpirationAwareness {

	private MentorStore $mentorStore;
	private ILoadBalancer $growthLB;
	protected WANObjectCache $wanCache;

	/**
	 * @param WANObjectCache $wanCache
	 * @param MentorStore $mentorStore
	 * @param ILoadBalancer $growthLB
	 */
	public function __construct(
		WANObjectCache $wanCache,
		MentorStore $mentorStore,
		ILoadBalancer $growthLB
	) {
		$this->wanCache = $wanCache;
		$this->mentorStore = $mentorStore;
		$this->growthLB = $growthLB;
	}

	/**
	 * @param UserIdentity $mentor
	 * @return string
	 */
	private function makeCacheKey( UserIdentity $mentor ): string {
		return $this->wanCache->makeKey(
			'GrowthExperiments',
			'MenteeOverviewDataProvider',
			__CLASS__,
			'Mentor',
			$mentor->getId()
		);
	}

	/**
	 * Invalidate cache for given mentor
	 * @param UserIdentity $mentor
	 */
	public function invalidateCacheForMentor( UserIdentity $mentor ): void {
		$this->wanCache->delete( $this->makeCacheKey( $mentor ) );
	}

	/**
	 * Decode data for particular mentee
	 *
	 * @param stdClass $row
	 * @return array
	 */
	private function formatDataForMentee( stdClass $row ): array {
		$input = FormatJson::decode( $row->mentee_data, true );
		$input['user_id'] = $row->mentee_id;
		if ( !array_key_exists( 'last_active', $input ) ) {
			$input['last_active'] = $input['last_edit'] ?? $input['registration'];
		}

		return $input;
	}

	/**
	 * @inheritDoc
	 */
	public function getFormattedDataForMentor( UserIdentity $mentor ): array {
		$method = __METHOD__;
		return $this->wanCache->getWithSetCallback(
			$this->makeCacheKey( $mentor ),
			self::TTL_DAY,
			function ( $oldValue, &$ttl, &$setOpts ) use ( $mentor, $method ) {
				$mentees = $this->mentorStore->getMenteesByMentor( $mentor, MentorStore::ROLE_PRIMARY );
				if ( $mentees === [] ) {
					$ttl = self::TTL_HOUR;
					return [];
				}

				$menteeIds = array_map( static function ( $mentee ) {
					return $mentee->getId();
				}, $mentees );

				$res = $this->growthLB->getConnection( DB_REPLICA )->newSelectQueryBuilder()
					->select( [ 'mentee_id', 'mentee_data' ] )
					->from( 'growthexperiments_mentee_data' )
					->where( [ 'mentee_id' => $menteeIds ] )
					->caller( $method )
					->fetchResultSet();
				$data = [];
				foreach ( $res as $row ) {
					$data[] = $this->formatDataForMentee( $row );
				}
				return $data;
			}
		);
	}

	/**
	 * Fetch MenteeOverview data for a given mentee
	 *
	 * This is useful in other parts of GrowthExperiments that wish
	 * to reuse data MenteeOverview has available (Personalized praise, for
	 * example).
	 *
	 * @param UserIdentity $mentee
	 * @return array|null Formatted data if exists; null otherwise
	 */
	public function getFormattedDataForMentee( UserIdentity $mentee ): ?array {
		$res = $this->growthLB->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'mentee_id', 'mentee_data' ] )
			->from( 'growthexperiments_mentee_data' )
			->conds( [
				'mentee_id' => $mentee->getId()
			] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$res ) {
			// mentee not found
			return null;
		}

		return $this->formatDataForMentee( $res );
	}
}
