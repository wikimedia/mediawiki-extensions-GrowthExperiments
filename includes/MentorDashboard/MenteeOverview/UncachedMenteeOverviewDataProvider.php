<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use ChangeTags;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\User\ActorMigration;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
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

	private MentorStore $mentorStore;

	private NameTableStore $changeTagDefStore;

	private ActorMigration $actorMigration;

	private UserIdentityLookup $userIdentityLookup;
	private TempUserConfig $tempUserConfig;

	private IConnectionProvider $mainConnProvider;

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
	 * @param TempUserConfig $tempUserConfig
	 * @param IConnectionProvider $mainConnProvider
	 */
	public function __construct(
		MentorStore $mentorStore,
		NameTableStore $changeTagDefStore,
		ActorMigration $actorMigration,
		UserIdentityLookup $userIdentityLookup,
		TempUserConfig $tempUserConfig,
		IConnectionProvider $mainConnProvider
	) {
		$this->setLogger( new NullLogger() );

		$this->mentorStore = $mentorStore;
		$this->changeTagDefStore = $changeTagDefStore;
		$this->actorMigration = $actorMigration;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->tempUserConfig = $tempUserConfig;
		$this->mainConnProvider = $mainConnProvider;
	}

	private function getReadConnection(): IReadableDatabase {
		return $this->mainConnProvider->getReplicaDatabase( false, 'vslow' );
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
			->caller( __METHOD__ )
			->fetchLocalUserIdentities();

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
	 *  * user is not a temporary account (safety check should temp users end up as mentees)
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

		$dbr = $this->getReadConnection();

		$menteeIds = $dbr->newSelectQueryBuilder()
			->select( 'up_user' )
			->from( 'user_properties' )
			->where( [
				'up_property' => HomepageHooks::HOMEPAGE_PREF_ENABLE,
				'up_value' => '1',
				'up_user' => $menteeIds,
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		if ( $menteeIds === [] ) {
			return [];
		}

		$menteeIdsWithMentorshipDisabled = $dbr->newSelectQueryBuilder()
			->select( 'up_user' )
			->from( 'user_properties' )
			->where( [
				'up_property' => MentorManager::MENTORSHIP_ENABLED_PREF,
				// sanity check, should never match (1 is the default value)
				$dbr->expr( 'up_value', '!=', '1' ),
				'up_user' => $menteeIds,
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		$menteeIds = array_diff(
			$menteeIds,
			$menteeIdsWithMentorshipDisabled,
		);
		if ( $menteeIds === [] ) {
			return [];
		}

		$menteeIdsWithBotGroup = $dbr->newSelectQueryBuilder()
			->select( 'ug_user' )
			->from( 'user_groups' )
			->where( [
				'ug_group' => 'bot',
				'ug_user' => $menteeIds,
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		$menteeIds = array_diff(
			$menteeIds,
			$menteeIdsWithBotGroup,
		);
		if ( $menteeIds === [] ) {
			return [];
		}

		$menteeIdsWithInfinityBlock = $dbr->newSelectQueryBuilder()
			->select( 'bt_user' )
			->from( 'block' )
			->join( 'block_target', null, [
				'bt_id=bl_target'
			] )
			->where( [
				'bt_user' => $menteeIds,
				'bl_expiry' => $dbr->getInfinity(),
				// not an IP block
				$dbr->expr( 'bt_user', '!=', null )
			] )
			->caller( __METHOD__ )
			->fetchFieldValues();
		$menteeIds = array_diff(
			$menteeIds,
			$menteeIdsWithInfinityBlock,
		);
		if ( $menteeIds === [] ) {
			return [];
		}

		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [
				'user_id',
				'has_edits' => 'user_editcount > 0'
			] )
			->from( 'user' )
			->where( [
				// filter to mentees only
				'user_id' => $menteeIds,

				// only users who either made an edit or registered less than 2 weeks ago
				$dbr->expr( 'user_editcount', '>', 0 )
					->or( 'user_registration', '>', $dbr->timestamp(
						(int)wfTimestamp( TS_UNIX ) - 2 * 7 * self::SECONDS_DAY
					) ),
			] )
			->caller( __METHOD__ );

		// exclude temporary accounts, if enabled (T341389)
		if ( $this->tempUserConfig->isKnown() ) {
			foreach ( $this->tempUserConfig->getMatchPatterns() as $pattern ) {
				$queryBuilder->andWhere(
					$dbr->expr( 'user_name', IExpression::NOT_LIKE, $pattern->toLikeValue( $dbr ) )
				);
			}
		}

		$res = $queryBuilder->fetchResultSet();

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

		$rows = $this->getReadConnection()->newSelectQueryBuilder()
			->select( [
				'actor_user',
				'last_edit' => 'MAX(rev_timestamp)'
			] )
			->from( 'revision' )
			->join( 'actor', null, 'rev_actor = actor_id' )
			->where( [
				'actor_user' => $userIds,
			] )
			->caller( __METHOD__ )
			->groupBy( 'actor_user' )
			->fetchResultSet();
		$res = [];
		foreach ( $rows as $row ) {
			$res[$row->actor_user] = $row->last_edit;
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
			$res[$userId]['last_active'] = $userData['last_edit'] ?? $userData['registration'] ?? null;
			if ( $res[$userId]['last_active'] === null ) {
				$this->logger->error(
					__METHOD__ . ': Registration and last_edit timestamps not found for user ID {userId}',
					[
						'userId' => $userId,
						'exception' => new \RuntimeException
					]
				);
			}
		}
		return $res;
	}

	/**
	 * @param int[] $userIds
	 * @return array
	 */
	private function getUsernames( array $userIds ): array {
		$startTime = microtime( true );

		$rows = $this->getReadConnection()->newSelectQueryBuilder()
			->select( [ 'user_id', 'user_name' ] )
			->from( 'user' )
			->where( [ 'user_id' => $userIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();
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

		$dbr = $this->getReadConnection();
		$queryInfo = $this->actorMigration->getJoin( 'rev_user' );
		$taggedEditsSubquery = $dbr->newSelectQueryBuilder()
			->select( [
				'rev_user' => $queryInfo['fields']['rev_user'],
				'ct_rev_id'
			] )
			->from( 'change_tag' )
			->join( 'revision', null, 'rev_id=ct_rev_id' )
			->tables( $queryInfo['tables'] )
			->where( [
				'actor_user' => $userIds,
				'ct_tag_id' => $tagIds
			] )
			->joinConds( $queryInfo['joins'] )
			->caller( __METHOD__ );
		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'user_id', 'tagged' => 'COUNT(ct_rev_id)' ] )
			->from( 'user' )
			->leftJoin( $taggedEditsSubquery, 'tagged_edits', 'rev_user=user_id' )
			->where( [ 'user_id' => $userIds ] )
			->caller( __METHOD__ )
			->groupBy( 'user_id' )
			->fetchResultSet();

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
		$rows = $this->getReadConnection()->newSelectQueryBuilder()
			->select( [ 'user_id', 'user_registration' ] )
			->from( 'user' )
			->where( [ 'user_id' => $userIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();
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
		$userNamesAsStrings = [];
		'@phan-var array<string> $userNamesAsStrings';
		$userIdentities = iterator_to_array( $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIds )
			->caller( __METHOD__ )
			->fetchUserIdentities() );
		array_walk(
			$userIdentities,
			static function ( UserIdentity $value, $key ) use ( &$users, &$userNamesAsStrings ) {
				$username = str_replace( ' ', '_', $value->getName() );
				$userNamesAsStrings[] = $username;
				$users[$username] = $value->getId();
			}
		);

		$rows = $this->getReadConnection()->newSelectQueryBuilder()
			->select( [ 'log_title', 'blocks' => 'COUNT(log_id)' ] )
			->from( 'logging' )
			->where( [
				'log_type' => 'block',
				'log_action' => 'block',
				'log_namespace' => NS_USER,
				'log_title' => $userNamesAsStrings
			] )
			->groupBy( 'log_title' )
			->caller( __METHOD__ )
			->fetchResultSet();

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

		$rows = $this->getReadConnection()->newSelectQueryBuilder()
			->select( [ 'user_id', 'user_editcount' ] )
			->from( 'user' )
			->where( [ 'user_id' => $userIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$res = [];
		foreach ( $rows as $row ) {
			$res[$row->user_id] = (int)$row->user_editcount;
		}

		$this->storeProfilingData( 'editcount', microtime( true ) - $startTime );

		return $res;
	}
}
