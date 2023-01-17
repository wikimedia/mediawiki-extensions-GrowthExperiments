<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use FormatJson;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\UserIdentity;
use WANObjectCache;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Rdbms\IDatabase;

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
	/** @var MentorStore */
	private $mentorStore;

	/** @var IDatabase */
	private $growthDbr;

	/** @var WANObjectCache */
	protected $wanCache;

	/**
	 * @param WANObjectCache $wanCache
	 * @param MentorStore $mentorStore
	 * @param IDatabase $growthDbr
	 */
	public function __construct(
		WANObjectCache $wanCache,
		MentorStore $mentorStore,
		IDatabase $growthDbr
	) {
		$this->wanCache = $wanCache;
		$this->mentorStore = $mentorStore;
		$this->growthDbr = $growthDbr;
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
	 * @inheritDoc
	 */
	public function getFormattedDataForMentor( UserIdentity $mentor ): array {
		return $this->wanCache->getWithSetCallback(
			$this->makeCacheKey( $mentor ),
			self::TTL_DAY,
			function ( $oldValue, &$ttl, &$setOpts ) use ( $mentor ) {
				$mentees = $this->mentorStore->getMenteesByMentor( $mentor, MentorStore::ROLE_PRIMARY );
				if ( $mentees === [] ) {
					$ttl = self::TTL_HOUR;
					return [];
				}

				$menteeIds = array_map( static function ( $mentee ) {
					return $mentee->getId();
				}, $mentees );

				$res = $this->growthDbr->select(
					'growthexperiments_mentee_data',
					[ 'mentee_id', 'mentee_data' ],
					[
						'mentee_id' => $menteeIds
					]
				);
				$data = [];
				foreach ( $res as $row ) {
					$tmp = FormatJson::decode( $row->mentee_data, true );
					$tmp['user_id'] = $row->mentee_id;
					if ( !array_key_exists( 'last_active', $tmp ) ) {
						$tmp['last_active'] = $tmp['last_edit'] ?? $tmp['registration'];
					}
					$data[] = $tmp;
				}
				return $data;
			}
		);
	}
}
