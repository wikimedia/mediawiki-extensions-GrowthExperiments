<?php

namespace GrowthExperiments;

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Helper class for user registration related queries.
 *
 * user_registration is not indexed so filtering or paging based on it is very slow on large wikis.
 * It is monotonic to a very good approximation though, so once we can find a user_id matching the
 * given registration timestamp, we can filter/page by primary key. This class provides some helper
 * functionality for that.
 */
class UserRegistrationLookupHelper {

	/**
	 * Find the first user_id with a registration date >= $registrationDate.
	 * On large wikis this is a slow operation.
	 * @param IDatabase $dbr
	 * @param int|string $registrationTimestamp Registration time in any format known by
	 *   ConvertibleTimestamp.
	 * @return int|null User ID, or null if no user has registered on or after that timestamp.
	 */
	public static function findFirstUserIdForRegistrationTimestamp(
		IDatabase $dbr,
		$registrationTimestamp
	): ?int {
		$registrationTimestamp = $dbr->timestamp( $registrationTimestamp );
		$queryBuilder = new SelectQueryBuilder( $dbr );
		$queryBuilder->table( 'user' );
		$queryBuilder->field( 'user_id' );
		$queryBuilder->where( "user_registration >= " . $dbr->addQuotes( $registrationTimestamp ) );
		$queryBuilder->orderBy( 'user_id', SelectQueryBuilder::SORT_ASC );
		$queryBuilder->limit( 1 );
		$queryBuilder->caller( __METHOD__ );
		$userId = $queryBuilder->fetchField();
		return $userId === false ? null : (int)$userId;
	}

}
