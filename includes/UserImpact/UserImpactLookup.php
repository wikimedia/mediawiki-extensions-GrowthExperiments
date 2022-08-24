<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;

interface UserImpactLookup {

	/**
	 * Retrieve impact data for a given user.
	 * @param UserIdentity $user
	 * @param bool $useLatest Whether to use the latest data. Implementations might use this
	 *   flag to skip cache lookups. Expensive data might be omitted when the flag is set.
	 * @return UserImpact|null
	 */
	public function getUserImpact( UserIdentity $user, bool $useLatest = false ): ?UserImpact;

}
