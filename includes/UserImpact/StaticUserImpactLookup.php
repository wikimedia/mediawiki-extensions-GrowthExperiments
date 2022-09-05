<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;

class StaticUserImpactLookup implements UserImpactLookup {

	use ExpensiveUserImpactFallbackTrait;

	/** @var UserImpact[] User ID => user impact */
	private $userImpacts;

	/**
	 * @param UserImpact[] $userImpacts User ID => user impact
	 */
	public function __construct( array $userImpacts ) {
		$this->userImpacts = $userImpacts;
	}

	/** @inheritDoc */
	public function getUserImpact( UserIdentity $user ): ?UserImpact {
		return $this->userImpacts[$user->getId()] ?? null;
	}

}
