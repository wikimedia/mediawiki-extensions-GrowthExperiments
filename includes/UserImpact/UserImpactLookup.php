<?php

namespace GrowthExperiments\UserImpact;

use IDBAccessObject;
use MediaWiki\User\UserIdentity;

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
	 * @return ExpensiveUserImpact|null
	 */
	public function getExpensiveUserImpact(
		UserIdentity $user, int $flags = IDBAccessObject::READ_NORMAL
	): ?ExpensiveUserImpact;

}
