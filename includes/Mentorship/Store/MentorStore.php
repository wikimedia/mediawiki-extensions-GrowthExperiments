<?php

namespace GrowthExperiments\Mentorship\Store;

use DBAccessObjectUtils;
use IDBAccessObject;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;
use WANObjectCache;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;

abstract class MentorStore implements IDBAccessObject, ExpirationAwareness {
	/** @var string */
	public const ROLE_PRIMARY = 'primary';

	/** @var string */
	public const ROLE_BACKUP = 'backup';

	/** @var string[] */
	public const ROLES = [
		self::ROLE_PRIMARY,
		self::ROLE_BACKUP
	];

	/** @var WANObjectCache */
	protected $wanCache;

	/** @var array Cache key =>¨value; custom in-process cache */
	protected $inProcessCache = [];

	/** @var bool */
	protected $wasPosted;

	/**
	 * @param WANObjectCache $wanCache
	 * @param bool $wasPosted
	 */
	public function __construct(
		WANObjectCache $wanCache,
		bool $wasPosted
	) {
		$this->wanCache = $wanCache;
		$this->wasPosted = $wasPosted;
	}

	/**
	 * Helper to generate cache key for a mentee
	 * @param UserIdentity $user Mentee's username
	 * @param string $mentorRole
	 * @return string Cache key
	 */
	protected function makeLoadMentorCacheKey(
		UserIdentity $user,
		string $mentorRole
	): string {
		return $this->wanCache->makeKey(
			'GrowthExperiments',
			'MentorStore', __CLASS__,
			'Mentee', $user->getId(),
			'Mentor', $mentorRole
		);
	}

	/**
	 * Invalidates mentor cache for loadMentorUser
	 * @param UserIdentity $user Who will have their cache invalidated
	 * @param string $mentorRole
	 */
	protected function invalidateMentorCache( UserIdentity $user, string $mentorRole ): void {
		$key = $this->makeLoadMentorCacheKey( $user, $mentorRole );
		$this->wanCache->delete(
			$key
		);
		unset( $this->inProcessCache[$key] );
	}

	/**
	 * Code helper for validating mentor type
	 *
	 * @param string $mentorRole
	 * @return bool True when valid, false otherwise
	 */
	private function validateMentorRole( string $mentorRole ): bool {
		return in_array( $mentorRole, self::ROLES );
	}

	/**
	 * Get the mentor assigned to this user, if it exists.
	 * @param UserIdentity $mentee
	 * @param string $mentorRole One of MentorStore::ROLE_* constants
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return UserIdentity|null
	 */
	public function loadMentorUser(
		UserIdentity $mentee,
		string $mentorRole,
		$flags = self::READ_NORMAL
	): ?UserIdentity {
		if ( !$this->validateMentorRole( $mentorRole ) ) {
			throw new InvalidArgumentException( "Invalid \$mentorRole passed: $mentorRole" );
		}

		if ( DBAccessObjectUtils::hasFlags( $flags, self::READ_LATEST ) ) {
			$this->invalidateMentorCache( $mentee, $mentorRole );
		}

		$cacheKey = $this->makeLoadMentorCacheKey( $mentee, $mentorRole );
		if ( isset( $this->inProcessCache[$cacheKey] ) ) {
			return $this->inProcessCache[$cacheKey];
		}

		$res = $this->wanCache->getWithSetCallback(
			$cacheKey,
			self::TTL_DAY,
			function () use ( $mentee, $mentorRole, $flags ) {
				return $this->loadMentorUserUncached( $mentee, $mentorRole, $flags );
			}
		);
		$this->inProcessCache[$cacheKey] = $res;
		return $res;
	}

	/**
	 * Load mentor user with no cache
	 *
	 * @param UserIdentity $mentee
	 * @param string $mentorRole One of MentorStore::ROLE_* constants
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return UserIdentity|null
	 */
	abstract protected function loadMentorUserUncached(
		UserIdentity $mentee,
		string $mentorRole,
		$flags
	): ?UserIdentity;

	/**
	 * Return mentees who are mentored by given mentor
	 *
	 * @param UserIdentity $mentor
	 * @param string|null $mentorRole Passing null is deprecated since 1.39.
	 * @param bool $includeHiddenUsers
	 * @param int $flags
	 * @return UserIdentity[]
	 */
	abstract public function getMenteesByMentor(
		UserIdentity $mentor,
		?string $mentorRole = null,
		bool $includeHiddenUsers = false,
		int $flags = 0
	): array;

	/**
	 * Checks whether a mentor has any mentees assigned
	 *
	 * @param UserIdentity $mentor
	 * @param string $mentorRole
	 * @param bool $includeHiddenUsers
	 * @param int $flags
	 * @return bool
	 */
	public function hasAnyMentees(
		UserIdentity $mentor,
		string $mentorRole,
		bool $includeHiddenUsers = false,
		int $flags = 0
	): bool {
		return $this->getMenteesByMentor(
			$mentor, $mentorRole, $includeHiddenUsers, $flags
		) !== [];
	}

	/**
	 * Assign a mentor to this user, overriding any previous assignments.
	 *
	 * This method can be safely called on GET requests.
	 *
	 * The actual logic for changing mentor is in setMentorForUserInternal, this method
	 * only validates mentor type and calls the internal one.
	 *
	 * @param UserIdentity $mentee
	 * @param UserIdentity|null $mentor Null to drop the relationship
	 * @param string $mentorRole One of MentorStore::ROLE_* constants
	 */
	public function setMentorForUser(
		UserIdentity $mentee,
		?UserIdentity $mentor,
		string $mentorRole
	): void {
		if ( !$this->validateMentorRole( $mentorRole ) ) {
			throw new InvalidArgumentException( "Invalid \$mentorRole passed: $mentorRole" );
		}

		$this->setMentorForUserInternal( $mentee, $mentor, $mentorRole );

		$this->invalidateMentorCache( $mentee, $mentorRole );

		// Set the mentor in the in-process cache
		$this->inProcessCache[$this->makeLoadMentorCacheKey( $mentee, $mentorRole )] = $mentor;
	}

	/**
	 * Actual logic for setting a mentor
	 * @param UserIdentity $mentee
	 * @param UserIdentity|null $mentor Set to null to drop the relationship
	 * @param string $mentorRole
	 */
	abstract protected function setMentorForUserInternal(
		UserIdentity $mentee,
		?UserIdentity $mentor,
		string $mentorRole
	): void;

	/**
	 * Drop mentor/mentee relationship for a given user
	 *
	 * @param UserIdentity $mentee
	 */
	public function dropMenteeRelationship( UserIdentity $mentee ): void {
		foreach ( self::ROLES as $role ) {
			$this->setMentorForUser( $mentee, null, $role );
		}
	}
}
