<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use BagOStuff;
use FormatJson;
use GrowthExperiments\Mentorship\Store\MentorStore;
use HashBagOStuff;
use MediaWiki\User\UserIdentity;
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
class DatabaseMenteeOverviewDataProvider implements MenteeOverviewDataProvider {
	/** @var MentorStore */
	private $mentorStore;

	/** @var IDatabase */
	private $growthDbr;

	/** @var BagOStuff */
	protected $cache;

	/** @var int */
	protected $cacheTtl = 0;

	/**
	 * @param MentorStore $mentorStore
	 * @param IDatabase $growthDbr
	 */
	public function __construct(
		MentorStore $mentorStore,
		IDatabase $growthDbr
	) {
		$this->mentorStore = $mentorStore;
		$this->growthDbr = $growthDbr;

		$this->cache = new HashBagOStuff();
	}

	/**
	 * Use a different cache. (Default is in-process caching only.)
	 * @param BagOStuff $cache
	 * @param int $ttl Cache expiry (0 for unlimited).
	 */
	public function setCache( BagOStuff $cache, int $ttl ) {
		$this->cache = $cache;
		$this->cacheTtl = $ttl;
	}

	/**
	 * @param UserIdentity $mentor
	 * @return string
	 */
	private function makeCacheKey( UserIdentity $mentor ): string {
		return $this->cache->makeKey(
			'GrowthExperiments',
			'MenteeOverviewDataProvider',
			__CLASS__,
			'Mentor',
			$mentor->getId()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getFormattedDataForMentor( UserIdentity $mentor ): array {
		$mentees = $this->mentorStore->getMenteesByMentor( $mentor, MentorStore::ROLE_PRIMARY );
		if ( $mentees === [] ) {
			return [];
		}

		$menteeIds = array_map( static function ( $mentee ) {
			return $mentee->getId();
		}, $mentees );

		return $this->cache->getWithSetCallback(
			$this->makeCacheKey( $mentor ),
			$this->cacheTtl,
			function () use ( $menteeIds ) {
				$res = $this->growthDbr->select(
					'growthexperiments_mentee_data',
					[ 'mentee_id', 'mentee_data' ],
					[
						'mentee_id' => $menteeIds
					]
				);
				$data = [];
				foreach ( $res as $row ) {
					$data[$row->mentee_id] = FormatJson::decode( $row->mentee_data, true );
				}
				return $data;
			}
		);
	}
}
