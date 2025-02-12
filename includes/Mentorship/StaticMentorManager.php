<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use GrowthExperiments\WikiConfigException;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;

/**
 * MentorManager implementation for local testing and development.
 * Uses a predefined user => mentor mapping.
 */
class StaticMentorManager implements IMentorManager {

	/** @var Mentor[] */
	private array $mentors;

	/** @var Mentor[] */
	private array $backupMentors;

	/**
	 * @param Mentor[] $mentors username => (primary) mentor
	 * @param Mentor[] $backupMentors username => backup mentor
	 */
	public function __construct( array $mentors, array $backupMentors = [] ) {
		$this->mentors = $mentors;
		$this->backupMentors = $backupMentors;
	}

	/** @inheritDoc */
	public function getMentorForUserIfExists(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): ?Mentor {
		return $this->getMentorForUserSafe( $user );
	}

	/** @inheritDoc */
	public function getMentorForUserSafe(
		UserIdentity $user,
		string $role = MentorStore::ROLE_PRIMARY
	): ?Mentor {
		if ( $role === MentorStore::ROLE_PRIMARY ) {
			return $this->mentors[$user->getName()] ?? null;
		} elseif ( $role === MentorStore::ROLE_BACKUP ) {
			return $this->backupMentors[$user->getName()] ?? null;
		} else {
			throw new InvalidArgumentException( 'Invalid role' );
		}
	}

	/** @inheritDoc */
	public function getEffectiveMentorForUser( UserIdentity $menteeUser ): Mentor {
		$mentor = $this->getEffectiveMentorForUserSafe( $menteeUser );
		if ( !$mentor ) {
			throw new WikiConfigException( __METHOD__ . ': No effective mentor for ' . $menteeUser->getName() );
		}
		return $mentor;
	}

	/** @inheritDoc */
	public function getEffectiveMentorForUserSafe( UserIdentity $menteeUser ): ?Mentor {
		return $this->getMentorForUserSafe( $menteeUser, MentorStore::ROLE_PRIMARY ) ??
			$this->getMentorForUserSafe( $menteeUser, MentorStore::ROLE_BACKUP );
	}

	/** @inheritDoc */
	public function newMentorFromUserIdentity(
		UserIdentity $mentorUser,
		?UserIdentity $menteeUser = null
	): Mentor {
		foreach ( $this->mentors as $mentee => $mentor ) {
			if ( $mentor->getUserIdentity()->equals( $mentorUser ) ) {
				return $mentor;
			}
		}
		throw new InvalidArgumentException( 'Invalid mentor passed' );
	}

	/** @inheritDoc */
	public function getMentorshipStateForUser( UserIdentity $user ): int {
		return IMentorManager::MENTORSHIP_ENABLED;
	}

	/** @inheritDoc */
	public function getRandomAutoAssignedMentor(
		UserIdentity $mentee,
		array $excluded = []
	): UserIdentity {
		$autoAssignedMentors = array_values( $this->mentors );
		return $autoAssignedMentors[rand( 0, count( $autoAssignedMentors ) - 1 )]->getUserIdentity();
	}

	/** @inheritDoc */
	public function setMentorshipStateForUser( UserIdentity $user, int $state ): void {
	}
}
