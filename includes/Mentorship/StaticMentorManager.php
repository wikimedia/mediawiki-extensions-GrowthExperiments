<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\WikiConfigException;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;
use Title;

/**
 * MentorManager implementation for local testing and development.
 * Uses a predefined user => mentor mapping.
 */
class StaticMentorManager extends MentorManager {

	/** @var array */
	private $mentors;

	/**
	 * @param Mentor[] $mentors username => mentor
	 */
	public function __construct( array $mentors ) {
		$this->mentors = $mentors;
	}

	/** @inheritDoc */
	public function getMentorForUserIfExists( UserIdentity $user ): ?Mentor {
		return $this->getMentorForUserSafe( $user );
	}

	/** @inheritDoc */
	public function getMentorForUser( UserIdentity $user ): Mentor {
		$mentor = $this->getMentorForUserSafe( $user );
		if ( !$mentor ) {
			throw new WikiConfigException( __METHOD__ . ': No mentor for {user}',
				[ 'user' => $user->getName() ] );
		}
		return $mentor;
	}

	/** @inheritDoc */
	public function getMentorForUserSafe( UserIdentity $user ): ?Mentor {
		return $this->mentors[$user->getName()] ?? null;
	}

	/** @inheritDoc */
	public function newMentorFromUserIdentity(
		UserIdentity $mentorUser,
		?UserIdentity $menteeUser = null
	): Mentor {
		foreach ( $this->mentors as $mentee => $mentor ) {
			if ( $mentor->getMentorUser()->equals( $mentorUser ) ) {
				return $mentor;
			}
		}
		throw new InvalidArgumentException( 'Invalid mentor passed' );
	}

	/** @inheritDoc */
	public function getAutoAssignedMentors(): array {
		return array_unique( array_values( array_map( static function ( Mentor $mentor ) {
			return $mentor->getMentorUser()->getName();
		}, $this->mentors ) ) );
	}

	/** @inheritDoc */
	public function getManuallyAssignedMentors(): array {
		return [];
	}

	/** @inheritDoc */
	public function getAutoMentorsListTitle(): ?Title {
		return null;
	}

	/** @inheritDoc */
	public function isMentorshipEnabledForUser( UserIdentity $user ): bool {
		return true;
	}

	/** @inheritDoc */
	public function getRandomAutoAssignedMentor(
		UserIdentity $mentee,
		array $excluded = []
	): UserIdentity {
		$autoAssignedMentors = array_values( $this->mentors );
		return $autoAssignedMentors[rand( 0, count( $autoAssignedMentors ) - 1 )]->getMentorUser();
	}
}
