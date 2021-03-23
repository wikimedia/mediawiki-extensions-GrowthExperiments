<?php

namespace GrowthExperiments\Mentorship\Store;

use HashBagOStuff;
use MediaWiki\User\UserIdentity;

abstract class MentorStore {
	/** @var HashBagOStuff */
	protected $cache;

	/** @var int */
	protected $cacheTtl = 0;

	public function __construct() {
		$this->cache = new HashBagOStuff();
	}

	/**
	 * Helper to generate cache key for a mentee
	 * @param UserIdentity $user Mentee's username
	 * @return string Cache key
	 */
	protected function makeCacheKey( UserIdentity $user ): string {
		return $this->cache->makeKey( 'GrowthExperiments', 'MentorStore', __CLASS__,
			'Mentee', $user->getId() );
	}

	/**
	 * Invalidates mentor cache for loadMentorUser
	 * @param UserIdentity $user Who will have their cache invalidated
	 */
	protected function invalidateMentorCache( UserIdentity $user ): void {
		$this->cache->delete(
			$this->makeCacheKey( $user )
		);
	}

	/**
	 * Get the mentor assigned to this user, if it exists.
	 * @param UserIdentity $mentee
	 * @return UserIdentity|null
	 */
	public function loadMentorUser( UserIdentity $mentee ): ?UserIdentity {
		return $this->cache->getWithSetCallback(
			$this->makeCacheKey( $mentee ),
			$this->cacheTtl,
			function () use ( $mentee ) {
				return $this->loadMentorUserUncached( $mentee );
			}
		);
	}

	/**
	 * Load mentor user with no cache
	 *
	 * @param UserIdentity $mentee
	 * @return UserIdentity|null
	 */
	abstract protected function loadMentorUserUncached( UserIdentity $mentee ): ?UserIdentity;

	/**
	 * Assign a mentor to this user, overriding any previous assignments.
	 * Normally this would be one of the mentors listed by getAutoAssignedMentors(), but
	 * that is not enforced.
	 * This method can be safely called on GET requests.
	 * @param UserIdentity $mentee
	 * @param UserIdentity $mentor
	 */
	abstract public function setMentorForUser( UserIdentity $mentee, UserIdentity $mentor ): void;
}
