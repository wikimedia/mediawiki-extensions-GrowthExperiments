<?php

namespace GrowthExperiments\Mentorship\Store;

use InvalidArgumentException;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IDBAccessObject;

abstract class MentorStore implements LoggerAwareInterface {
	use LoggerAwareTrait;

	public const ROLE_PRIMARY = 'primary';
	public const ROLE_BACKUP = 'backup';

	public const ROLES = [
		self::ROLE_PRIMARY,
		self::ROLE_BACKUP
	];

	protected WANObjectCache $wanCache;

	/** @var array Cache key => value; custom in-process cache */
	protected array $inProcessCache = [];
	protected bool $wasPosted;

	public function __construct(
		WANObjectCache $wanCache,
		bool $wasPosted
	) {
		$this->wanCache = $wanCache;
		$this->wasPosted = $wasPosted;

		$this->setLogger( new NullLogger() );
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
		$flags = IDBAccessObject::READ_NORMAL
	): ?UserIdentity {
		if ( !$this->validateMentorRole( $mentorRole ) ) {
			throw new InvalidArgumentException( "Invalid \$mentorRole passed: $mentorRole" );
		}

		if ( DBAccessObjectUtils::hasFlags( $flags, IDBAccessObject::READ_LATEST ) ) {
			$this->invalidateMentorCache( $mentee, $mentorRole );
		}

		$cacheKey = $this->makeLoadMentorCacheKey( $mentee, $mentorRole );
		if ( isset( $this->inProcessCache[$cacheKey] ) ) {
			return $this->inProcessCache[$cacheKey];
		}

		$res = $this->wanCache->getWithSetCallback(
			$cacheKey,
			WANObjectCache::TTL_DAY,
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
	 * @param string $mentorRole
	 * @param bool $includeHiddenUsers
	 * @param bool $includeInactiveUsers
	 * @param int $flags
	 * @return UserIdentity[]
	 */
	abstract public function getMenteesByMentor(
		UserIdentity $mentor,
		string $mentorRole,
		bool $includeHiddenUsers = false,
		bool $includeInactiveUsers = true,
		int $flags = 0
	): array;

	/**
	 * Checks whether a mentor has any mentees assigned
	 *
	 * @param UserIdentity $mentor
	 * @param string $mentorRole
	 * @param bool $includeHiddenUsers Deprecated (and ignored) since 1.43
	 * @param int $flags
	 * @return bool
	 */
	abstract public function hasAnyMentees(
		UserIdentity $mentor,
		string $mentorRole,
		bool $includeHiddenUsers = true,
		int $flags = 0
	): bool;

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
		$this->invalidateIsMenteeActive( $mentee );

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

	/**
	 * Is an user considered a mentee?
	 *
	 * Equivalent to "Do they have a primary mentor assigned?"
	 *
	 * @param UserIdentity $user
	 * @param int $flags
	 * @return bool
	 */
	public function isMentee(
		UserIdentity $user,
		int $flags = IDBAccessObject::READ_NORMAL
	): bool {
		return $this->loadMentorUser(
			$user,
			self::ROLE_PRIMARY,
			$flags
		) !== null;
	}

	/**
	 * Make cache key for isMenteeActive()
	 *
	 * @param UserIdentity $user
	 * @return string
	 */
	private function makeIsMenteeActiveCacheKey( UserIdentity $user ): string {
		return $this->wanCache->makeKey(
			'GrowthExperiments',
			'MentorStore', __CLASS__,
			'Mentee', $user->getId(),
			'IsActive'
		);
	}

	/**
	 * Invalidates cache for isMenteeActive()
	 *
	 * @param UserIdentity $user
	 */
	protected function invalidateIsMenteeActive( UserIdentity $user ): void {
		$this->wanCache->delete( $this->makeIsMenteeActiveCacheKey( $user ) );
	}

	/**
	 * Is the mentee active?
	 *
	 * This will be used by MentorFilterHooks to only include
	 * recently active mentees, to avoid errors like T293182.
	 *
	 * A mentee should be marked as active if they edited less than
	 * $wgRCMaxAge seconds ago.
	 *
	 * @param UserIdentity $mentee
	 * @return bool|null
	 */
	public function isMenteeActive( UserIdentity $mentee ): ?bool {
		return $this->wanCache->getWithSetCallback(
			$this->makeIsMenteeActiveCacheKey( $mentee ),
			WANObjectCache::TTL_DAY,
			function () use ( $mentee ) {
				return $this->isMenteeActiveUncached( $mentee );
			}
		);
	}

	/**
	 * Is the mentee active?
	 *
	 * Bypasses caching.
	 *
	 * @see MentorStore::isMenteeActive()
	 * @param UserIdentity $mentee
	 * @return bool|null
	 */
	abstract protected function isMenteeActiveUncached( UserIdentity $mentee ): ?bool;

	/**
	 * Mark the mentee as active
	 *
	 * This will be used by MentorFilterHooks to only include
	 * recently active mentees, to avoid errors like T293182.
	 *
	 * A mentee should be marked as active if they edited less than
	 * $wgRCMaxAge seconds ago.
	 *
	 * Method should only make a write query if the mentee is not
	 * already marked as active.
	 *
	 * @param UserIdentity $mentee
	 */
	abstract public function markMenteeAsActive( UserIdentity $mentee ): void;

	/**
	 * Mark a mentee as inactive
	 *
	 * This will be used by MentorFilterHooks to only include
	 * recently active mentees, to avoid errors like T293182.
	 *
	 * A mentee should be marked as inactive if they edited more than
	 * $wgRCMaxAge seconds ago.
	 *
	 * Method should only make a write query if the mentee is not
	 * already marked as inactive.
	 *
	 * @param UserIdentity $mentee
	 */
	abstract public function markMenteeAsInactive( UserIdentity $mentee ): void;
}
