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
	 * @return Title|null
	 */
	public function getAutoMentorsListTitle(): ?Title {
		return null;
	}

}
