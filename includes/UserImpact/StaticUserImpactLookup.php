<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDBAccessObject;

class StaticUserImpactLookup implements UserImpactLookup, UserImpactStore {

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
	public function getUserImpact( UserIdentity $user, int $flags = IDBAccessObject::READ_NORMAL ): ?UserImpact {
		return $this->userImpacts[$user->getId()] ?? null;
	}

	/** @inheritDoc */
	public function setUserImpact( UserImpact $userImpact ): void {
		$this->userImpacts[$userImpact->getUser()->getId()] = $userImpact;
	}
}
