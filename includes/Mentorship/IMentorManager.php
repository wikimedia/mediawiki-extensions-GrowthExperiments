<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;

/**
 * A service for handling mentors.
 */
interface IMentorManager {

	public const MENTORSHIP_DISABLED = 0;
	public const MENTORSHIP_ENABLED = 1;
	public const MENTORSHIP_OPTED_OUT = 2;

	public const MENTORSHIP_STATES = [
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
	public function getMentorForUserIfExists(
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
	public function getMentorForUser(
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
	public function getMentorForUserSafe(
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
	public function getEffectiveMentorForUser( UserIdentity $menteeUser ): Mentor;

	/**
	 * Get the effective mentor assigned to this user, suppressing configuration errors.
	 *
	 * This returns the primary mentor if they're active, otherwise,
	 * it returns the backup mentor.
	 *
	 * @param UserIdentity $menteeUser
	 * @return Mentor|null
	 */
	public function getEffectiveMentorForUserSafe( UserIdentity $menteeUser ): ?Mentor;

	/**
	 * Checks state of mentorship for an user
	 *
	 * @param UserIdentity $user
	 * @return int One of MentorManager::MENTORSHIP_*
	 */
	public function getMentorshipStateForUser( UserIdentity $user ): int;

	/**
	 * Set state of mentorship for an user
	 *
	 * @param UserIdentity $user
	 * @param int $state One of MentorManager::MENTORSHIP_*
	 * @throws InvalidArgumentException In case of invalid $state
	 */
	public function setMentorshipStateForUser( UserIdentity $user, int $state ): void;

	/**
	 * Randomly selects a mentor from the available mentors.
	 *
	 * @param UserIdentity $mentee
	 * @param UserIdentity[] $excluded A list of users who should not be selected.
	 * @return UserIdentity|null The selected mentor; null if none available.
	 * @throws WikiConfigException If the mentor list is invalid.
	 */
	public function getRandomAutoAssignedMentor(
		UserIdentity $mentee, array $excluded = []
	): ?UserIdentity;
}
