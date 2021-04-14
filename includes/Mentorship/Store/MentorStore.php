<?php

namespace GrowthExperiments\Mentorship\Store;

use BagOStuff;
use CachedBagOStuff;
use DBAccessObjectUtils;
use HashBagOStuff;
use IDBAccessObject;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;

abstract class MentorStore implements IDBAccessObject {
	/**
	 * @var string
	 * As of now, we have only primary mentors, but this is done in anticipation of T227876 being
	 * done.
	 */
	public const ROLE_PRIMARY = 'primary';

	/** @var BagOStuff */
	protected $cache;

	/** @var BagOStuff */
	protected $innerCache;

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
		$this->innerCache = $cache;
		$this->cache = new CachedBagOStuff( $this->innerCache );
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
	 * As of now, the only allowed constant is MentorStore::MENTOR_PRIMARY;
	 * this will be more useful when T227876 is done.
	 *
	 * @param string $mentorRole
	 * @return bool True when valid, false otherwise
	 */
	private function validateMentorRole( string $mentorRole ): bool {
		return in_array( $mentorRole, [ self::ROLE_PRIMARY ] );
	}

	/**
	 * Get the mentor assigned to this user, if it exists.
	 * @param UserIdentity $mentee
	 * @param string $mentorRole One of MentorStore::MENTOR_ constants
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return UserIdentity|null
	 */
	public function loadMentorUser(
		UserIdentity $mentee,
		string $mentorRole = self::ROLE_PRIMARY,
		$flags = self::READ_NORMAL
	): ?UserIdentity {
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
	 * @internal Only to be used from MultiWriteMentorStore
	 * @param UserIdentity $mentee
	 * @param string $mentorRole One of MentorStore::MENTOR_ constants
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return UserIdentity|null
	 */
	abstract public function loadMentorUserUncached(
		UserIdentity $mentee,
		string $mentorRole,
		$flags
	): ?UserIdentity;

	/**
	 * Assign a mentor to this user, overriding any previous assignments.
	 *
	 * This method can be safely called on GET requests.
	 *
	 * The actual logic for changing mentor is in setMentorForUserInternal, this method
	 * only validates mentor type and calls the internal one.
	 *
	 * @param UserIdentity $mentee
	 * @param UserIdentity $mentor
	 * @param string $mentorRole One of MentorStore::MENTOR_ constants
	 */
	public function setMentorForUser(
		UserIdentity $mentee,
		UserIdentity $mentor,
		string $mentorRole = self::ROLE_PRIMARY
	): void {
		if ( !$this->validateMentorRole( $mentorRole ) ) {
			throw new InvalidArgumentException( "Invalid \$mentorRole passed: $mentorRole" );
		}

		$this->setMentorForUserInternal( $mentee, $mentor, $mentorRole );

		$this->invalidateMentorCache( $mentee, $mentorRole );

		// Set the mentor in the in-process cache
		$this->cache->set(
			$this->makeCacheKey( $mentee, $mentorRole ),
			$mentor,
			$this->cacheTtl,
			BagOStuff::WRITE_CACHE_ONLY
		);
	}

	/**
	 * Actual logic for
	 * @param UserIdentity $mentee
	 * @param UserIdentity $mentor
	 * @param string $mentorRole
	 */
	abstract protected function setMentorForUserInternal(
		UserIdentity $mentee,
		UserIdentity $mentor,
		string $mentorRole = self::ROLE_PRIMARY
	): void;
}
