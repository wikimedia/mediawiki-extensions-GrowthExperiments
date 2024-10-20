<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDBAccessObject;

interface UserImpactLookup {

	/**
	 * Retrieve impact data for a given user.
	 * @param UserIdentity $user
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return UserImpact|null
	 */
	public function getUserImpact( UserIdentity $user, int $flags = IDBAccessObject::READ_NORMAL ): ?UserImpact;

	/**
	 * Retrieve impact data for a given user, including expensive data.
	 * @param UserIdentity $user
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @param array $priorityArticles List of article titles in DBKey format. When fetching page view data, we should
	 *  first fetch data for these articles, then any others that are in the list of articles that the user has
	 *  edited.
	 * @return ExpensiveUserImpact|null
	 */
	public function getExpensiveUserImpact(
		UserIdentity $user,
		int $flags = IDBAccessObject::READ_NORMAL,
		array $priorityArticles = []
	): ?ExpensiveUserImpact;

}
