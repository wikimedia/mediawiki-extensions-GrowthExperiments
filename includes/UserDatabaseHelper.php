<?php

namespace GrowthExperiments;

use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Helper class for some user-related queries.
 */
class UserDatabaseHelper {

	private IDatabase $dbr;
	private UserFactory $userFactory;

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
		$queryBuilder = new SelectQueryBuilder( $this->dbr );
		$queryBuilder->table( 'revision' );
		$queryBuilder->join( 'page', null, 'page_id = rev_page' );
		$queryBuilder->field( '1' );
		$queryBuilder->where( [
			'rev_actor' => $user->getActorId(),
			'page_namespace' => NS_MAIN,
		] );
		// Look at the user's oldest edits - arbitrary choice, other than we want the method to be
		// deterministic. Can be done efficiently via the rev_actor_timestamp index.
		$queryBuilder->orderBy( 'rev_timestamp', SelectQueryBuilder::SORT_ASC );
		$queryBuilder->limit( $limit );
		$queryBuilder->caller( __METHOD__ );
		// Opting for code readability over the slightly more performant approach of doing the
		// same wrapping that Database::selectRowCount() does but with an outer LIMIT of 1.
		$rowCount = $queryBuilder->fetchRowCount();
		return $rowCount === $limit ? null : (bool)$rowCount;
	}

}
