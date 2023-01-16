<?php

namespace GrowthExperiments;

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Helper class for some user-related queries.
 */
class UserDatabaseHelper {

	private IDatabase $dbr;

	/**
	 * @param IDatabase $dbr Read handle to the database with the user table.
	 */
	public function __construct(
		IDatabase $dbr
	) {
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

}
