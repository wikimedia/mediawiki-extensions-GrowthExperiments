<?php

namespace GrowthExperiments\Mentorship\Store;

use DBAccessObjectUtils;
use HashBagOStuff;
use IDBAccessObject;
use MediaWiki\User\UserIdentity;

abstract class MentorStore implements IDBAccessObject {
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
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return UserIdentity|null
	 */
	public function loadMentorUser(
		UserIdentity $mentee,
		$flags = self::READ_NORMAL
	): ?UserIdentity {
		if ( DBAccessObjectUtils::hasFlags( $flags, self::READ_LATEST ) ) {
			$this->invalidateMentorCache( $mentee );
		}

		return $this->cache->getWithSetCallback(
			$this->makeCacheKey( $mentee ),
			$this->cacheTtl,
			function () use ( $mentee, $flags ) {
				return $this->loadMentorUserUncached( $mentee, $flags );
			}
		);
	}

	/**
	 * Load mentor user with no cache
	 *
	 * @param UserIdentity $mentee
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return UserIdentity|null
	 */
	abstract protected function loadMentorUserUncached( UserIdentity $mentee, $flags ): ?UserIdentity;

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
