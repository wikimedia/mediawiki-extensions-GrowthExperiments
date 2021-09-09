<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\WikiConfigException;
use MediaWiki\User\UserIdentity;
use Title;

/**
 * A service for handling mentors.
 */
abstract class MentorManager {

	/**
	 * Get the mentor assigned to this user, if it exists.
	 * @param UserIdentity $user
	 * @return Mentor|null
	 */
	abstract public function getMentorForUserIfExists( UserIdentity $user ): ?Mentor;

	/**
	 * Get the mentor assigned to this user.
	 * If the user did not have a mentor before, this will assign one on the fly.
	 * @param UserIdentity $user
	 * @return Mentor
	 * @throws WikiConfigException If it is not possible to obtain a mentor due to misconfiguration.
	 */
	abstract public function getMentorForUser( UserIdentity $user ): Mentor;

	/**
	 * Get the mentor assigned to this user. Suppress configuration errors and return null
	 * if a mentor cannot be assigned.
	 * @param UserIdentity $user
	 * @return Mentor|null
	 */
	abstract public function getMentorForUserSafe( UserIdentity $user ): ?Mentor;

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
		return in_array( $user->getName(), $this->getMentorsSafe() );
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
	 * Checks if mentorship is enabled
	 *
	 * @note See T287903 in Phabricator for use-case.
	 * @param UserIdentity $user
	 * @return bool
	 */
	abstract public function isMentorshipEnabledForUser( UserIdentity $user ): bool;
}
