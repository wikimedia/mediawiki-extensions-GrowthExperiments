<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;

class StaticUserImpactLookup implements UserImpactLookup {

	/** @var UserImpact[] User ID => user impact */
	private $userImpacts;

	/**
	 * @param UserImpact[] $userImpacts User ID => user impact
	 */
	public function __construct( array $userImpacts ) {
		$this->userImpacts = $userImpacts;
	}

	/** @inheritDoc */
	public function getUserImpact( UserIdentity $user, bool $useLatest = false ): ?UserImpact {
		return $this->userImpacts[$user->getId()] ?? null;
	}

}
