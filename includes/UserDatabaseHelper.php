<?php

namespace GrowthExperiments;

use LogicException;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Helper class for some user-related queries.
 */
class UserDatabaseHelper {

	private UserFactory $userFactory;
	private IDatabase $dbr;

	/**
	 * @param UserFactory $userFactory
	 * @param IDatabase $dbr Read handle to the database with the user table.
	 */
	public function __construct(
		UserFactory $userFactory,
		IDatabase $dbr
	) {
		$this->userFactory = $userFactory;
		$this->dbr = $dbr;
	}

	/**
	 * Find the first user_id with a registration date >= $registrationDate. On large wikis this
	 * can be a slow operation and should be only used in deferreds and similar
	 * non-performance-sensitive places.
	 *
	 * user_registration is not indexed so filtering or paging based on it is very slow on large
	 * wikis. It is monotonic to a very good approximation though, so once we can find the first
	 * user_id matching the given registration timestamp, we can filter/page by primary key.
	 * @param int|string $registrationTimestamp Registration time in any format known by
	 *   ConvertibleTimestamp.
	 * @return int|null User ID, or null if no user has registered on or after that timestamp.
	 */
	public function findFirstUserIdForRegistrationTimestamp( $registrationTimestamp ): ?int {
		$registrationTimestamp = $this->dbr->timestamp( $registrationTimestamp );
		$queryBuilder = new SelectQueryBuilder( $this->dbr );
		$queryBuilder->table( 'user' );
		$queryBuilder->field( 'user_id' );
		$queryBuilder->where( "user_registration >= " . $this->dbr->addQuotes( $registrationTimestamp ) );
		$queryBuilder->orderBy( 'user_id', SelectQueryBuilder::SORT_ASC );
		$queryBuilder->limit( 1 );
		$queryBuilder->caller( __METHOD__ );
		$userId = $queryBuilder->fetchField();
		return $userId === false ? null : (int)$userId;
	}

	/**
	 * Performant approximate check for whether the user has any edits in the main namespace.
	 * Will return null if the user's first $limit edits are all not in the main namespace.
	 * @param UserIdentity $userIdentity
	 * @param int $limit
	 * @return bool|null
	 */
	public function hasMainspaceEdits( UserIdentity $userIdentity, int $limit = 1000 ): ?bool {
		$user = $this->userFactory->newFromUserIdentity( $userIdentity );
		$query = new SelectQueryBuilder( $this->dbr );

		// Do an inner query that selects the user's last $limit edits. Aligns with the
		// rev_actor_timestamp index, so it will only need to scan up to $limit rows.
		$innerQuery = $query->newSubquery();
		$innerQuery->table( 'revision' );
		$innerQuery->join( 'page', null, 'page_id = rev_page' );
		$innerQuery->fields( [ 'rev_id', 'page_namespace' ] );
		$innerQuery->where( [
			'rev_actor' => $user->getActorId(),
		] );
		// Look at the user's oldest edits - arbitrary, other than we want a deterministic result.
		$innerQuery->orderBy( 'rev_timestamp', SelectQueryBuilder::SORT_ASC );
		$innerQuery->limit( $limit );
		$innerQuery->caller( __METHOD__ );

		// Now count the rows and the mainspace rows.
		$query->table( $innerQuery, 'first_1000_edits' );
		$nsMain = NS_MAIN;
		$query->fields( [ 'all_edits' => 'COUNT(*)', 'main_edits' => "SUM( page_namespace = $nsMain )" ] );
		$query->caller( __METHOD__ );

		$row = $query->fetchRow();
		// For the benefit of static type checks - aggregate query, cannot return empty result set.
		if ( $row === false ) {
			throw new LogicException( 'Unexpected empty result' );
		}
		return $row->main_edits ? true : ( $row->all_edits === $limit ? null : false );
	}

}
