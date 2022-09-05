<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;

trait ExpensiveUserImpactFallbackTrait {

	/** @inheritDoc */
	abstract public function getUserImpact( UserIdentity $user ): ?UserImpact;

	/** @inheritDoc */
	public function getExpensiveUserImpact( UserIdentity $user ): ?ExpensiveUserImpact {
		$userImpact = $this->getUserImpact( $user );
		if ( $userImpact instanceof ExpensiveUserImpact ) {
			return $userImpact;
		}
		return null;
	}

}
