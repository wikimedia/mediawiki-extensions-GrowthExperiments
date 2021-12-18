<?php

namespace GrowthExperiments\Mentorship;

use BagOStuff;
use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use HashBagOStuff;
use MediaWiki\User\UserIdentity;
use Title;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;

/**
 * A service for handling mentors.
 */
abstract class MentorManager implements ExpirationAwareness {

	/** @var BagOStuff */
	protected $cache;
	/** @var int */
	protected $cacheTtl;

	public function __construct() {
		$this->setCache( new HashBagOStuff(), self::TTL_INDEFINITE );
	}

	/**
	 * Use a different cache
	 *
	 * Default is in-process cache only.
	 *
	 * @param BagOStuff $cache
	 * @param int $ttl
	 */
	public function setCache( BagOStuff $cache, int $ttl ) {
		$this->cache = $cache;
		$this->cacheTtl = $ttl;
	}

	/**
	 * Invalidate cache of mentorship-related information
	 */
	abstract public function invalidateCache(): void;

	/** @var int */
	public const MENTORSHIP_DISABLED = 0;
	/** @var int */
	public const MENTORSHIP_ENABLED = 1;
	/** @var int */
	public const MENTORSHIP_OPTED_OUT = 2;

	/** @var int[] */
	protected const MENTORSHIP_STATES = [
		self::MENTORSHIP_DISABLED,
		self::MENTORSHIP_ENABLED,
		self::MENTORSHIP_OPTED_OUT
	];

	/**
	 * Get the mentor assigned to this user, if it exists.
	 * @param UserIdentity $user
	 * @param string $role MentorStore::ROLE_* constant
	 * @return Mentor|null
	 */
	abstract public function getMentorForUserIfExists(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): ?Mentor;

	/**
	 * Get the mentor assigned to this user.
	 * If the user did not have a mentor before, this will assign one on the fly.
	 * @param UserIdentity $user
	 * @param string $role MentorStore::ROLE_* constant
	 * @return Mentor
	 * @throws WikiConfigException If it is not possible to obtain a mentor due to misconfiguration.
	 */
	abstract public function getMentorForUser(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): Mentor;

	/**
	 * Get the mentor assigned to this user. Suppress configuration errors and return null
	 * if a mentor cannot be assigned.
	 * @param UserIdentity $user
	 * @param string $role MentorStore::ROLE_* constant
	 * @return Mentor|null
	 */
	abstract public function getMentorForUserSafe(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): ?Mentor;

	/**
	 * Get the effective mentor assigned to this user.
	 *
	 * This returns the primary mentor if they're active, otherwise,
	 * it returns the backup mentor.
	 *
	 * @param UserIdentity $menteeUser
	 * @return Mentor
	 * @throws WikiConfigException If it is not possible to obtain a mentor due to misconfiguration.
	 */
	abstract public function getEffectiveMentorForUser( UserIdentity $menteeUser ): Mentor;

	/**
	 * Get the effective mentor assigned to this user, suppressing configuration errors.
	 *
	 * This returns the primary mentor if they're active, otherwise,
	 * it returns the backup mentor.
	 *
	 * @param UserIdentity $menteeUser
	 * @return Mentor|null
	 */
	abstract public function getEffectiveMentorForUserSafe( UserIdentity $menteeUser ): ?Mentor;

	/**
	 * Construct a Mentor object for given UserIdentity
	 *
	 * This is useful for when you know the mentor's username, and need MentorManager to provide
	 * specific details about them.
	 *
	 * @param UserIdentity $mentorUser
	 * @param UserIdentity|null $menteeUser If passed, may be used to customize message using
	 * mentee's username.
	 * @return Mentor
	 */
	abstract public function newMentorFromUserIdentity(
		UserIdentity $mentorUser,
		?UserIdentity $menteeUser = null
	): Mentor;

	/**
	 * Get all mentors, regardless on their auto-assignment status
	 * @return string[] List of mentors usernames.
	 * @throws WikiConfigException If the mentor list cannot be fetched due to misconfiguration.
	 */
	public function getMentors(): array {
		return array_unique(
			array_merge(
				$this->getAutoAssignedMentors(),
				$this->getManuallyAssignedMentors()
			)
		);
	}

	/**
	 * Get all mentors, regardless of their auto-assignment status
	 *
	 * This does the same thing as getMentors(), but it suppresses any instance
	 * of WikiConfigException (and returns an empty array instead).
	 *
	 * @return string[]
	 */
	public function getMentorsSafe(): array {
		try {
			return $this->getMentors();
		} catch ( WikiConfigException $e ) {
			return [];
		}
	}

	/**
	 * Checks if an user is a mentor (regardless of their auto-assignment status)
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function isMentor( UserIdentity $user ): bool {
		return $user->isRegistered() && in_array( $user->getName(), $this->getMentorsSafe() );
	}

	/**
	 * Get all the mentors who are automatically assigned to mentees.
	 * @return string[] List of mentor usernames.
	 * @throws WikiConfigException If the mentor list cannot be fetched due to misconfiguration.
	 */
	abstract public function getAutoAssignedMentors(): array;

	/**
	 * Get a list of mentors who are not automatically assigned to mentees.
	 * @throws WikiConfigException If the mentor list cannot be fetched due to misconfiguration.
	 * @return string[] List of mentors usernames.
	 */
	abstract public function getManuallyAssignedMentors(): array;

	/**
	 * Link to the list of mentors, if there is any
	 *
	 * @return Title|null Null only if the page is not configured
	 * @throws WikiConfigException If the mentor page cannot be fetched due to misconfiguration.
	 */
	abstract public function getAutoMentorsListTitle(): ?Title;

	/**
	 * Checks state of mentorship for an user
	 *
	 * @param UserIdentity $user
	 * @return int One of MentorManager::MENTORSHIP_*
	 */
	abstract public function getMentorshipStateForUser( UserIdentity $user ): int;

	/**
	 * Randomly selects a mentor from the available mentors.
	 *
	 * @param UserIdentity $mentee
	 * @param UserIdentity[] $excluded A list of users who should not be selected.
	 * @return UserIdentity|null The selected mentor; null if none available.
	 * @throws WikiConfigException If the mentor list is invalid.
	 */
	abstract public function getRandomAutoAssignedMentor(
		UserIdentity $mentee, array $excluded = []
	): ?UserIdentity;
}
