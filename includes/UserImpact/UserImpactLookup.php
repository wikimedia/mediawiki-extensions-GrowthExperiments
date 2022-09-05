<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;

interface UserImpactLookup {

	/**
	 * Retrieve impact data for a given user.
	 * @param UserIdentity $user
	 * @return UserImpact|null
	 */
	public function getUserImpact( UserIdentity $user ): ?UserImpact;

	/**
	 * Retrieve impact data for a given user, including expensive data.
	 * @param UserIdentity $user
	 * @return ExpensiveUserImpact|null
	 */
	public function getExpensiveUserImpact( UserIdentity $user ): ?ExpensiveUserImpact;

}
