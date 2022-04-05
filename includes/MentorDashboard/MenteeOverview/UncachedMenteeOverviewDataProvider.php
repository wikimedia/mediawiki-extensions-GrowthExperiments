<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use ActorMigration;
use ChangeTags;
use ExtensionRegistry;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Provides data about interesting mentees mentored by a particular mentor
 *
 * WARNING: This class implements no caching, and it may be only used from CLI
 * scripts or jobs.
 */
class UncachedMenteeOverviewDataProvider implements MenteeOverviewDataProvider {
	use LoggerAwareTrait;

	/** @var int Number of seconds in a day */
	private const SECONDS_DAY = 86400;

	/** @var MentorStore */
	private $mentorStore;

	/** @var NameTableStore */
	private $changeTagDefStore;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/** @var IDatabase */
	private $mainDbr;

	/** @var array Cache used by getLastEditTimestampForUsers */
	private $lastTimestampCache = [];

	/**
	 * @var array Profiling information
	 *
	 * Stored by storeProfilingData, can be printed from
	 * updateMenteeData.php maintenance script.
	 */
	private $profilingInfo = [];

	/**
	 * @param MentorStore $mentorStore
	 * @param NameTableStore $changeTagDefStore
	 * @param ActorMigration $actorMigration
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param IDatabase $mainDbr
	 */
	public function __construct(
		MentorStore $mentorStore,
		NameTableStore $changeTagDefStore,
		ActorMigration $actorMigration,
		UserIdentityLookup $userIdentityLookup,
		IDatabase $mainDbr
	) {
		$this->setLogger( new NullLogger() );

		$this->mentorStore = $mentorStore;
		$this->changeTagDefStore = $changeTagDefStore;
		$this->actorMigration = $actorMigration;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->mainDbr = $mainDbr;
	}

	/**
	 * Do stuff that needs to happen before calculating the data
	 */
	private function resetService(): void {
		$this->lastTimestampCache = [];
		$this->profilingInfo = [];
	}

	/**
	 * Get profiling information
	 *
	 * @internal Only use from updateMenteeData.php
	 * @return array
	 */
	public function getProfilingInfo(): array {
		return $this->profilingInfo;
	}

	/**
	 * @param string $section
	 * @param float $seconds
	 */
	private function storeProfilingData( string $section, float $seconds ): void {
		$this->profilingInfo[$section] = $seconds;
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
	 * Return local user IDs of all globally locked mentees
	 *
	 * If CentralAuth is not installed, this returns an empty array (as
	 * locking is not possible in that case).
	 *
	 * If CentralAuth is available, CentralAuthServices::getGlobalUserSelectQueryBuilderFactory
	 * is used to get locked mentees only. The select query builder does not impose any explicit
	 * limit on number of users that can be processed at once. UncachedMenteeOverviewDataProvider
	 * runs certain queries similar to the CentralAuth extension, which run fine with the current
	 * number of mentees (as of March 2022, the upper bound is 42,716).
	 *
	 * @param UserIdentity[] $mentees
	 * @return int[]
	 */
	private function getLockedMenteesIds( array $mentees ): array {
		if (
			!ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ||
			$mentees === []
		) {
			return [];
		}

		if (
			!method_exists( CentralAuthServices::class, 'getGlobalUserSelectQueryBuilderFactory' )
		) {
			$this->logger->error(
				'Old version of CentralAuth found, CentralAuthServices::getGlobalUserSelectQueryBuilderFactory' .
				' was not found'
			);
			return [];
		}

		$userIdentities = CentralAuthServices::getGlobalUserSelectQueryBuilderFactory()
			->newGlobalUserSelectQueryBuilder()
			->whereUserNames( array_map( static function ( UserIdentity $user ) {
				return $user->getName();
			}, $mentees ) )
			->whereLocked( true )
			->fetchLocalUserIdentitites();

		return array_map( static function ( UserIdentity $user ) {
			return $user->getId();
		}, iterator_to_array( $userIdentities ) );
	}

	/**
	 * Filter mentees according to business rules
	 *
	 * Only mentees that meet all of the following conditions
	 * should be considered:
	 *  * user is not a bot
	 *  * user is not indefinitely blocked
	 *  * user is not globally locked (via CentralAuth's implementation)
	 *  * user registered less than 2 weeks ago OR made at least one edit in the last 6 months
	 *
	 * @param UserIdentity $mentor
	 * @return int[] User IDs of the mentees
	 */
	private function getFilteredMenteesForMentor( UserIdentity $mentor ): array {
		$startTime = microtime( true );

		$mentees = $this->mentorStore->getMenteesByMentor( $mentor, MentorStore::ROLE_PRIMARY );
		$menteeIds = array_diff(
			$this->getIds( $mentees ),
			$this->getLockedMenteesIds( $mentees )
		);

		if ( $menteeIds === [] ) {
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
				'user_id' => $menteeIds,

				// ensure mentees have homepage enabled
				'user_id IN (' . $this->mainDbr->selectSQLText(
					'user_properties',
					'up_user',
					[
						'up_property' => HomepageHooks::HOMEPAGE_PREF_ENABLE,
						'up_value' => 1
					]
				) . ')',

				// ensure mentees do not have mentorship disabled
				'user_id NOT IN (' . $this->mainDbr->selectSQLText(
					'user_properties',
					'up_user',
					[
						'up_property' => MentorPageMentorManager::MENTORSHIP_ENABLED_PREF,
						// sanity check, should never match (1 is the default value)
						'up_value != 1',
					]
				) . ')',

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
							(int)wfTimestamp( TS_UNIX ) - 2 * 7 * self::SECONDS_DAY
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
				$editingUsers[] = (int)$row->user_id;
			} else {
				$notEditingUsers[] = (int)$row->user_id;
			}
		}

		$this->storeProfilingData( 'filtermentees', microtime( true ) - $startTime );

		return array_merge(
			$notEditingUsers,
			$this->filterMenteesByLastEdit( $editingUsers )
		);
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getLastEditTimestampForUsersInternal( array $userIds ): array {
		$startTime = microtime( true );

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

		$this->storeProfilingData(
			'edittimestampinternal',
			microtime( true ) - $startTime
		);
		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getLastEditTimestampForUsers( array $userIds ): array {
		if ( $userIds === [] ) {
			return [];
		}

		$data = array_intersect_key( $this->lastTimestampCache, array_fill_keys( $userIds, true ) );
		$notInCache = array_diff( $userIds, array_keys( $this->lastTimestampCache ) );
		if ( $notInCache ) {
			$new = $this->getLastEditTimestampForUsersInternal( $notInCache );
			$data += $new;
			$this->lastTimestampCache += $new;
		}
		return $data;
	}

	/**
	 * Filter provided user IDs to IDs of users who edited up to 6 months ago
	 *
	 * @param array $allUserIds
	 * @return int[]
	 */
	private function filterMenteesByLastEdit( array $allUserIds ): array {
		if ( $allUserIds === [] ) {
			return [];
		}

		$allLastEdits = $this->getLastEditTimestampForUsers( $allUserIds );
		$userIds = [];
		foreach ( $allLastEdits as $userId => $lastEdit ) {
			$secondsSinceLastEdit = (int)wfTimestamp( TS_UNIX ) -
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
		$this->resetService();

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
		foreach ( $res as $userId => $userData ) {
			$res[$userId]['last_active'] = $userData['last_edit'] ?? $userData['registration'];
		}
		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getUsernames( array $userIds ): array {
		$startTime = microtime( true );

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

		$this->storeProfilingData( 'usernames', microtime( true ) - $startTime );

		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getRevertedEditsForUsers( array $userIds ): array {
		$startTime = microtime( true );
		$res = $this->getTaggedEditsForUsers(
			[ ChangeTags::TAG_REVERTED ],
			$userIds
		);
		$this->storeProfilingData( 'reverted', microtime( true ) - $startTime );
		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getQuestionsAskedForUsers( array $userIds ): array {
		$startTime = microtime( true );
		$res = $this->getTaggedEditsForUsers(
			[
				Mentorship::MENTORSHIP_HELPPANEL_QUESTION_TAG,
				Mentorship::MENTORSHIP_MODULE_QUESTION_TAG
			],
			$userIds
		);
		$this->storeProfilingData( 'questions', microtime( true ) - $startTime );
		return $res;
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
		$startTime = microtime( true );
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
		$this->storeProfilingData( 'registration', microtime( true ) - $startTime );
		return $res;
	}

	/**
	 * Get number of blocks placed against the mentees
	 *
	 * @param int[] $userIds
	 * @return int[]
	 */
	private function getBlocksForUsers( array $userIds ): array {
		if ( $userIds === [] ) {
			return [];
		}

		$startTime = microtime( true );

		// fetch usernames (assoc. array; username => user ID)
		// NOTE: username has underscores, not spaces
		$users = [];
		$userIdentities = iterator_to_array( $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIds )
			->fetchUserIdentities() );
		array_walk(
			$userIdentities,
			static function ( UserIdentity $value, $key ) use ( &$users ) {
				$users[str_replace( ' ', '_', $value->getName() )] = $value->getId();
			}
		);

		$rows = $this->mainDbr->select(
			[ 'logging' ],
			[ 'log_title', 'blocks' => 'COUNT(log_id)' ],
			[
				'log_type' => 'block',
				'log_action' => 'block',
				'log_namespace' => 2,
				'log_title' => array_keys( $users )
			],
			__METHOD__,
			[
				'GROUP BY' => 'log_title',
			]
		);

		$res = [];
		foreach ( $rows as $row ) {
			$res[$users[$row->log_title]] = (int)$row->blocks;
		}

		// fill missing IDs with zeros
		$missingIds = array_diff( $userIds, array_keys( $res ) );
		foreach ( $missingIds as $id ) {
			$res[$id] = 0;
		}

		$this->storeProfilingData( 'blocks', microtime( true ) - $startTime );

		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return int[]
	 */
	private function getEditCountsForUsers( array $userIds ): array {
		$startTime = microtime( true );

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

		$this->storeProfilingData( 'editcount', microtime( true ) - $startTime );

		return $res;
	}
}
