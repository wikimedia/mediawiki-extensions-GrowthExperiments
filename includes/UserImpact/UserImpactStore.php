<?php

namespace GrowthExperiments\UserImpact;

interface UserImpactStore extends UserImpactLookup {

	/**
	 * Store user impact data.
	 * @param UserImpact $userImpact
	 * @return void
	 */
	public function setUserImpact( UserImpact $userImpact ): void;

}
