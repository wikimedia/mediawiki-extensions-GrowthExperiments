<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

abstract class MentorProvider {

	protected LoggerInterface $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
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
	 * Checks if a user is a mentor (regardless of their auto-assignment status)
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function isMentor( UserIdentity $user ): bool {
		return $user->isRegistered() && isset( $this->getMentors()[$user->getId()] );
	}

	/**
	 * Get all mentors, regardless on their auto-assignment status
	 * @return Mentor[] List of mentors, keyed by user ID
	 */
	abstract public function getMentors(): array;

	/**
	 * Get all the mentors who are automatically assigned to mentees.
	 * @return Mentor[] List of mentors, keyed by user ID
	 */
	public function getAutoAssignedMentors(): array {
		return array_filter(
			$this->getMentors(),
			static fn ( Mentor $mentor ) => $mentor->getWeight() !== IMentorWeights::WEIGHT_NONE
		);
	}

	/**
	 * Get weighted list of automatically assigned mentors
	 *
	 * If a mentor is configured to receive more mentees than others, the returned array will
	 * have them multiple times.
	 *
	 * @return Mentor[] Array of mentors
	 */
	public function getWeightedAutoAssignedMentors(): array {
		$result = [];
		foreach ( $this->getAutoAssignedMentors() as $mentor ) {
			for ( $i = 0; $i < $mentor->getWeight(); $i++ ) {
				$result[] = $mentor;
			}
		}
		return $result;
	}

	/**
	 * Get a list of mentors who are not automatically assigned to mentees.
	 * @return Mentor[] List of mentors, keyed by user ID
	 */
	public function getManuallyAssignedMentors(): array {
		return array_filter(
			$this->getMentors(),
			static fn ( Mentor $mentor ) => $mentor->getWeight() === IMentorWeights::WEIGHT_NONE
		);
	}

	/**
	 * Invalidate any cached mentor data
	 *
	 * This method should be implemented by providers that use caching
	 * to ensure cache consistency when mentor data changes.
	 *
	 * @since 1.43
	 */
	public function invalidateMentorsCache(): void {
	}
}
