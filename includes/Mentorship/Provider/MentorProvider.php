<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class MentorProvider {
	use LoggerAwareTrait;

	public function __construct() {
		$this->setLogger( new NullLogger() );
	}

	/** @var int Maximum mentor intro length. */
	public const INTRO_TEXT_LENGTH = 240;

	/**
	 * Returns a Title to the signup page, if it exists
	 *
	 * @return Title|null
	 */
	abstract public function getSignupTitle(): ?Title;

	/**
	 * Construct a Mentor object for given UserIdentity
	 *
	 * This is useful for when you know the mentor's username, and need MentorManager to provide
	 * specific details about them.
	 *
	 * The caller needs to ensure $mentorUser is a mentor (otherwise, implementation may
	 * throw). You can use MentorProvider::isMentor() for that purpose.
	 *
	 * @param UserIdentity $mentorUser Caller needs to ensure $mentorUser is a mentor
	 * @param UserIdentity|null $menteeUser If passed, may be used to customize message using
	 * mentee's username.
	 * @return Mentor
	 */
	abstract public function newMentorFromUserIdentity(
		UserIdentity $mentorUser,
		?UserIdentity $menteeUser = null
	): Mentor;

	/**
	 * Checks if an user is a mentor (regardless of their auto-assignment status)
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function isMentor( UserIdentity $user ): bool {
		return $user->isRegistered() && array_reduce(
			$this->getMentors(),
			static function ( bool $carry, UserIdentity $mentorUser ) use ( $user ) {
				if ( $carry ) {
					return true;
				}
				return $user->equals( $mentorUser );
			},
			false
		);
	}

	/**
	 * Get all mentors, regardless on their auto-assignment status
	 * @return UserIdentity[] List of mentors
	 */
	abstract public function getMentors(): array;

	/**
	 * Get all the mentors who are automatically assigned to mentees.
	 * @return UserIdentity[] List of mentors.
	 */
	abstract public function getAutoAssignedMentors(): array;

	/**
	 * Get weighted list of automatically assigned mentors
	 *
	 * If a mentor is configured to receive more mentees than others, the returned array will
	 * have their name multiple times.
	 *
	 * @return UserIdentity[] Array of mentors
	 */
	abstract public function getWeightedAutoAssignedMentors(): array;

	/**
	 * Get a list of mentors who are not automatically assigned to mentees.
	 * @return UserIdentity[] List of mentors.
	 */
	abstract public function getManuallyAssignedMentors(): array;
}
