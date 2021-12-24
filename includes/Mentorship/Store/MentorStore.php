<?php

namespace GrowthExperiments\Mentorship\Store;

use BagOStuff;
use CachedBagOStuff;
use DBAccessObjectUtils;
use HashBagOStuff;
use IDBAccessObject;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;

abstract class MentorStore implements IDBAccessObject {
	/** @var string */
	public const ROLE_PRIMARY = 'primary';

	/** @var string */
	public const ROLE_BACKUP = 'backup';

	/** @var string[] */
	public const ROLES = [
		self::ROLE_PRIMARY,
		self::ROLE_BACKUP
	];

	/** @var BagOStuff */
	protected $cache;

	/** @var int */
	protected $cacheTtl = 0;

	/** @var bool */
	protected $wasPosted;

	/**
	 * @param bool $wasPosted
	 */
	public function __construct(
		bool $wasPosted
	) {
		$this->cache = new HashBagOStuff();
		$this->wasPosted = $wasPosted;
	}

	/**
	 * Use a different cache. (Default is in-process caching only.)
	 * @param BagOStuff $cache
	 * @param int $ttl Cache expiry (0 for unlimited).
	 */
	public function setCache( BagOStuff $cache, int $ttl ) {
		$this->cache = new CachedBagOStuff( $cache );
		$this->cacheTtl = $ttl;
	}

	/**
	 * Helper to generate cache key for a mentee
	 * @param UserIdentity $user Mentee's username
	 * @param string $mentorRole
	 * @return string Cache key
	 */
	protected function makeCacheKey( UserIdentity $user, string $mentorRole ): string {
		return $this->cache->makeKey(
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
		$this->cache->delete(
			$this->makeCacheKey( $user, $mentorRole )
		);
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
	 * @param string|null $mentorRole One of MentorStore::ROLE_* constants; passing no value is
	 * deprecated (results in using ROLE_PRIMARY).
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return UserIdentity|null
	 */
	public function loadMentorUser(
		UserIdentity $mentee,
		?string $mentorRole = null,
		$flags = self::READ_NORMAL
	): ?UserIdentity {
		if ( $mentorRole === null ) {
			wfDeprecated( __METHOD__ . ' with no role parameter', '1.38' );
			$mentorRole = self::ROLE_PRIMARY;
		}

		if ( !$this->validateMentorRole( $mentorRole ) ) {
			throw new InvalidArgumentException( "Invalid \$mentorRole passed: $mentorRole" );
		}

		if ( DBAccessObjectUtils::hasFlags( $flags, self::READ_LATEST ) ) {
			$this->invalidateMentorCache( $mentee, $mentorRole );
		}

		return $this->cache->getWithSetCallback(
			$this->makeCacheKey( $mentee, $mentorRole ),
			$this->cacheTtl,
			function () use ( $mentee, $mentorRole, $flags ) {
				return $this->loadMentorUserUncached( $mentee, $mentorRole, $flags );
			}
		);
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
	 * Optionally allows to filter by role
	 *
	 * @param UserIdentity $mentor
	 * @param string|null $mentorRole
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
	 * Assign a mentor to this user, overriding any previous assignments.
	 *
	 * This method can be safely called on GET requests.
	 *
	 * The actual logic for changing mentor is in setMentorForUserInternal, this method
	 * only validates mentor type and calls the internal one.
	 *
	 * @param UserIdentity $mentee
	 * @param UserIdentity|null $mentor Null to drop the relationship
	 * @param string|null $mentorRole One of MentorStore::ROLE_* constants; passing no value is
	 * deprecated (results in ROLE_PRIMARY being used).
	 */
	public function setMentorForUser(
		UserIdentity $mentee,
		?UserIdentity $mentor,
		?string $mentorRole = null
	): void {
		if ( $mentorRole === null ) {
			wfDeprecated( __METHOD__ . ' with no role parameter', '1.38' );
			$mentorRole = self::ROLE_PRIMARY;
		}

		if ( !$this->validateMentorRole( $mentorRole ) ) {
			throw new InvalidArgumentException( "Invalid \$mentorRole passed: $mentorRole" );
		}

		$this->setMentorForUserInternal( $mentee, $mentor, $mentorRole );

		$this->invalidateMentorCache( $mentee, $mentorRole );

		// Set the mentor in the in-process cache
		$this->cache->set(
			$this->makeCacheKey( $mentee, $mentorRole ),
			$mentor ? new UserIdentityValue( $mentor->getId(), $mentor->getName() ) : null,
			$this->cacheTtl,
			BagOStuff::WRITE_CACHE_ONLY
		);
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
