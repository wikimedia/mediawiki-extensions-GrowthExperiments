<?php

namespace GrowthExperiments\UserImpact;

use IDBAccessObject;
use MediaWiki\User\UserIdentity;

trait ExpensiveUserImpactFallbackTrait {

	/** @inheritDoc */
	abstract public function getUserImpact(
		UserIdentity $user, int $flags = IDBAccessObject::READ_NORMAL
	): ?UserImpact;

	/** @inheritDoc */
	public function getExpensiveUserImpact(
		UserIdentity $user,
		int $flags = IDBAccessObject::READ_NORMAL,
		array $priorityArticles = []
	): ?ExpensiveUserImpact {
		$userImpact = $this->getUserImpact( $user, $flags );
		if ( $userImpact instanceof ExpensiveUserImpact ) {
			return $userImpact;
		}
		return null;
	}

}
