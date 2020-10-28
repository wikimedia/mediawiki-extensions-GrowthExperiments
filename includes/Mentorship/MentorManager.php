<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\WikiConfigException;
use MediaWiki\User\UserIdentity;

/**
 * A service for handling mentors.
 */
interface MentorManager {

	/**
	 * Get the mentor assigned to this user.
	 * If the user did not have a mentor before, this will assign one on the fly.
	 * @param UserIdentity $user
	 * @return Mentor
	 * @throws WikiConfigException If it is not possible to obtain a mentor due to misconfiguration.
	 */
	public function getMentorForUser( UserIdentity $user ): Mentor;

	/**
	 * Get the mentor assigned to this user. Suppress configuration errors and return null
	 * if a mentor cannot be assigned.
	 * @param UserIdentity $user
	 * @return Mentor|null
	 */
	public function getMentorForUserSafe( UserIdentity $user ): ?Mentor;

	/**
	 * Assign a mentor to this user, overriding any previous assignments.
	 * Normally this would be one of the mentors listed by getAvailableMentors(), but
	 * that is not enforced.
	 * This method can be safely called on GET requests.
	 * @param UserIdentity $user
	 * @param UserIdentity $mentor
	 */
	public function setMentorForUser( UserIdentity $user, UserIdentity $mentor ): void;

	/**
	 * Get all mentors, regardless on their auto-assignment status
	 *
	 * @todo Rewrite MentorManager to an abstract class and put a default logic here. See T266189.
	 *
	 * @return string[] List of mentors usernames.
	 * @throws WikiConfigException If the mentor list cannot be fetched due to misconfiguration.
	 */
	public function getMentors(): array;

	/**
	 * Get all the mentors who are automatically assigned to mentees.
	 * @return string[] List of mentor usernames.
	 * @throws WikiConfigException If the mentor list cannot be fetched due to misconfiguration.
	 */
	public function getAvailableMentors(): array;

	/**
	 * Get a list of mentors who are not automatically assigned to mentees.
	 * @throws WikiConfigException If the mentor list cannot be fetched due to misconfiguration.
	 * @return string[] List of mentors usernames.
	 */
	public function getManuallyAssignedMentors(): array;

}
