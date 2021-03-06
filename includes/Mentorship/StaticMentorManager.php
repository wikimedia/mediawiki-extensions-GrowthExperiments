<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\WikiConfigException;
use MediaWiki\User\UserIdentity;

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
			throw new WikiConfigException( __METHOD__ . ': No mentor for ' . $user->getName() );
		}
		return $mentor;
	}

	/** @inheritDoc */
	public function getMentorForUserSafe( UserIdentity $user ): ?Mentor {
		return $this->mentors[$user->getName()] ?? null;
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

}
