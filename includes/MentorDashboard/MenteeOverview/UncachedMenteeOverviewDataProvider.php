<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use ActorMigration;
use ChangeTags;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Provides data about interesting mentees mentored by a particular mentor
 *
 * WARNING: This class implements no caching, and it may not be used outside
 * of CLI scripts.
 */
class UncachedMenteeOverviewDataProvider implements MenteeOverviewDataProvider {
	/** @var int Number of seconds in a day */
	private const SECONDS_DAY = 86400;

	/** @var MentorStore */
	private $mentorStore;

	/** @var NameTableStore */
	private $changeTagDefStore;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var IDatabase */
	private $mainDbr;

	/**
	 * @param MentorStore $mentorStore
	 * @param NameTableStore $changeTagDefStore
	 * @param ActorMigration $actorMigration
	 * @param IDatabase $mainDbr
	 */
	public function __construct(
		MentorStore $mentorStore,
		NameTableStore $changeTagDefStore,
		ActorMigration $actorMigration,
		IDatabase $mainDbr
	) {
		$this->mentorStore = $mentorStore;
		$this->changeTagDefStore = $changeTagDefStore;
		$this->actorMigration = $actorMigration;
		$this->mainDbr = $mainDbr;
	}

	/**
	 * @param UserIdentity[] $users
	 * @return int[]
	 */
	private function getIds( array $users ): array {
		return array_map( static function ( $user ) {
			return $user->getId();
		}, $users );
	}

	/**
	 * Filter mentees according to business rules
	 *
	 * Only mentees that meet all of the following conditions
	 * should be considered:
	 *  * user is not a bot
	 *  * user is not indefinitely blocked
	 *  * user registered less than 2 weeks ago OR made at least one edit
	 *
	 * This does not filter users according to last edit made (which is also
	 * in the list of business rules), because doing so would require knowing the last
	 * edit timestamp. So, instead, we calculate last edit timestamp for all users,
	 * and then filter from there.
	 *
	 * @param UserIdentity $mentor
	 * @return int[] User IDs of the mentees
	 */
	private function getFilteredMenteesForMentor( UserIdentity $mentor ): array {
		$mentees = $this->mentorStore->getMenteesByMentor( $mentor );
		if ( $mentees === [] ) {
			return [];
		}

		$res = $this->mainDbr->select(
			'user',
			[
				'user_id',
				'has_edits' => 'user_editcount > 0'
			],
			[
				// filter to mentees only
				'user_id' => $this->getIds( $mentees ),

				// user is not a bot,
				'user_id NOT IN (' . $this->mainDbr->selectSQLText(
					'user_groups',
					'ug_user',
					[ 'ug_group' => 'bot' ]
				) . ')',

				// user is not indefinitely blocked
				'user_id NOT IN (' . $this->mainDbr->selectSQLText(
					'ipblocks',
					'ipb_user',
					[
						'ipb_expiry' => $this->mainDbr->getInfinity(),
						// not an IP block
						'ipb_user != 0',
					]
				) . ')',

				// only users who either made an edit or registered less than 2 weeks ago
				$this->mainDbr->makeList( [
					'user_editcount > 0',
					'user_registration > ' . $this->mainDbr->addQuotes(
						$this->mainDbr->timestamp(
							wfTimestamp( TS_UNIX ) - 2 * 7 * self::SECONDS_DAY
						)
					)
				], IDatabase::LIST_OR ),
			],
			__METHOD__
		);

		$editingUsers = [];
		$notEditingUsers = [];
		foreach ( $res as $row ) {
			if ( $row->has_edits ) {
				$editingUsers[] = $row->user_id;
			} else {
				$notEditingUsers[] = $row->user_id;
			}
		}

		return array_merge(
			$notEditingUsers,
			$this->filterMenteesByLastEdit( $editingUsers )
		);
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getLastEditTimestampForUsers( array $userIds ): array {
		$queryInfo = $this->actorMigration->getJoin( 'rev_user' );
		$rows = $this->mainDbr->select(
			[ 'revision' ] + $queryInfo['tables'],
			[
				'rev_user' => $queryInfo['fields']['rev_user'],
				'last_edit' => 'MAX(rev_timestamp)'
			],
			[
				$queryInfo['fields']['rev_user'] => $userIds,
			],
			__METHOD__,
			[
				'GROUP BY' => $queryInfo['fields']['rev_user'],
			],
			$queryInfo['joins']
		);
		$res = [];
		foreach ( $rows as $row ) {
			$res[$row->rev_user] = $row->last_edit;
		}
		return $res;
	}

	/**
	 * Filter provided user IDs to IDs of users who edited up to 6 months ago
	 *
	 * @param array $allUserIds
	 * @return int[]
	 */
	private function filterMenteesByLastEdit( array $allUserIds ): array {
		$allLastEdits = $this->getLastEditTimestampForUsers( $allUserIds );
		$userIds = [];
		foreach ( $allLastEdits as $userId => $lastEdit ) {
			$secondsSinceLastEdit = wfTimestamp( TS_UNIX ) -
				(int)ConvertibleTimestamp::convert(
					TS_UNIX,
					$lastEdit
				);
			if ( $secondsSinceLastEdit <= self::SECONDS_DAY * 6 * 30 ) {
				$userIds[] = $userId;
			}
		}
		return $userIds;
	}

	/**
	 * Calculates data for a given mentor's mentees
	 *
	 * @param UserIdentity $mentor
	 * @return array
	 */
	public function getFormattedDataForMentor( UserIdentity $mentor ): array {
		$userIds = $this->getFilteredMenteesForMentor( $mentor );
		if ( $userIds === [] ) {
			return [];
		}

		$mainData = [
			'username' => $this->getUsernames( $userIds ),
			'reverted' => $this->getRevertedEditsForUsers( $userIds ),
			'questions' => $this->getQuestionsAskedForUsers( $userIds ),
			'editcount' => $this->getEditCountsForUsers( $userIds ),
			'registration' => $this->getRegistrationTimestampForUsers( $userIds ),
			'last_edit' => $this->getLastEditTimestampForUsers( $userIds ),
			'blocks' => $this->getBlocksForUsers( $userIds ),
		];

		$res = [];
		foreach ( $mainData as $key => $data ) {
			foreach ( $data as $userId => $value ) {
				$res[$userId][$key] = $value;
			}
		}
		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getUsernames( array $userIds ): array {
		$rows = $this->mainDbr->select(
			'user',
			[ 'user_id', 'user_name' ],
			[
				'user_id' => $userIds
			],
			__METHOD__
		);
		$res = [];
		foreach ( $rows as $row ) {
			$res[$row->user_id] = $row->user_name;
		}
		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getRevertedEditsForUsers( array $userIds ): array {
		return $this->getTaggedEditsForUsers(
			[ ChangeTags::TAG_REVERTED ],
			$userIds
		);
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getQuestionsAskedForUsers( array $userIds ): array {
		return $this->getTaggedEditsForUsers(
			[
				Mentorship::MENTORSHIP_HELPPANEL_QUESTION_TAG,
				Mentorship::MENTORSHIP_MODULE_QUESTION_TAG
			],
			$userIds
		);
	}

	/**
	 * @param string[] $tags
	 * @param int[] $userIds
	 * @return int[]
	 */
	private function getTaggedEditsForUsers( array $tags, array $userIds ) {
		$tagIds = [];
		foreach ( $tags as $tag ) {
			try {
				$tagIds[] = $this->changeTagDefStore->getId( $tag );
			} catch ( NameTableAccessException $e ) {
				// Skip non-existing tags gracefully
			}
		}
		if ( $tagIds === [] ) {
			return array_fill_keys( $userIds, 0 );
		}

		$queryInfo = $this->actorMigration->getJoin( 'rev_user' );
		$taggedEditsSubquery = $this->mainDbr->buildSelectSubquery(
			[ 'change_tag', 'revision' ] + $queryInfo['tables'],
			[
				'rev_user' => $queryInfo['fields']['rev_user'],
				'ct_rev_id'
			],
			[
				'actor_user' => $userIds,
				'ct_tag_id' => $tagIds
			],
			__METHOD__,
			[],
			[
				'revision' => [ 'JOIN', 'rev_id=ct_rev_id' ],
			] + $queryInfo['joins']
		);
		$rows = $this->mainDbr->select(
			[ 'user', 'tagged_edits' => $taggedEditsSubquery ],
			[ 'user_id', 'tagged' => 'COUNT(ct_rev_id)' ],
			[ 'user_id' => $userIds ],
			__METHOD__,
			[
				'GROUP BY' => 'user_id',
			],
			[
				'tagged_edits' => [ 'LEFT JOIN', 'rev_user=user_id' ]
			]
		);

		$res = [];
		foreach ( $rows as $row ) {
			$res[$row->user_id] = (int)$row->tagged;
		}
		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getRegistrationTimestampForUsers( array $userIds ): array {
		$rows = $this->mainDbr->select(
			'user',
			[ 'user_id', 'user_registration' ],
			[ 'user_id' => $userIds ],
			__METHOD__
		);
		$res = [];
		foreach ( $rows as $row ) {
			$res[$row->user_id] = $row->user_registration;
		}
		return $res;
	}

	/**
	 * Get number of blocks placed against the mentees
	 *
	 * @param int[] $userIds
	 * @return int[]
	 */
	private function getBlocksForUsers( array $userIds ): array {
		$blocksMade = $this->mainDbr->buildSelectSubquery(
			[ 'logging', 'user' ],
			[ 'blocked_user' => 'user_id', 'log_id' ],
			[
				'log_type' => 'block',
				'log_action' => 'block',
				'user_id' => $userIds,
			],
			__METHOD__,
			[],
			[
				'user' => [ 'JOIN', 'REPLACE(log_title, "_", " ")=user_name' ]
			]
		);
		$rows = $this->mainDbr->select(
			[ 'user', 'blocks_made' => $blocksMade ],
			[ 'user_id', 'blocks' => 'COUNT(log_id)' ],
			[
				'user_id' => $userIds
			],
			__METHOD__,
			[
				'GROUP BY' => 'user_id',
			],
			[
				'blocks_made' => [ 'LEFT JOIN', 'user_id=blocked_user' ]
			]
		);
		$res = [];
		foreach ( $rows as $row ) {
			$res[$row->user_id] = (int)$row->blocks;
		}
		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return int[]
	 */
	private function getEditCountsForUsers( array $userIds ): array {
		$rows = $this->mainDbr->select(
			'user',
			[ 'user_id', 'user_editcount' ],
			[
				'user_id' => $userIds
			],
			__METHOD__
		);
		$res = [];
		foreach ( $rows as $row ) {
			$res[$row->user_id] = (int)$row->user_editcount;
		}
		return $res;
	}
}
