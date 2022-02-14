<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Mentorship\Mentor;
use InvalidArgumentException;
use MediaWiki\User\UserIdentity;
use Title;

/**
 * Static implementation of MentorProvider, created for use in tests
 */
class StaticMentorProvider extends MentorProvider {

	/** @var Mentor[] */
	private $autoMentors;

	/** @var Mentor[] */
	private $manualMentors;

	/**
	 * @param Mentor[] $autoMentors
	 * @param Mentor[] $manualMentors
	 */
	public function __construct(
		array $autoMentors,
		array $manualMentors = []
	) {
		parent::__construct();

		$this->autoMentors = $autoMentors;
		$this->manualMentors = $manualMentors;
	}

	/**
	 * @inheritDoc
	 */
	public function getSignupTitle(): ?Title {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function newMentorFromUserIdentity(
		UserIdentity $mentorUser, ?UserIdentity $menteeUser = null
	): Mentor {
		$mentors = array_merge( $this->autoMentors, $this->manualMentors );
		foreach ( $mentors as $mentor ) {
			if ( $mentor->getUserIdentity()->equals( $mentorUser ) ) {
				return $mentor;
			}
		}
		throw new InvalidArgumentException( 'Invalid mentor passed' );
	}

	/**
	 * @inheritDoc
	 */
	public function getAutoAssignedMentors(): array {
		return array_unique( array_values( array_map( static function ( Mentor $mentor ) {
			return $mentor->getUserIdentity()->getName();
		}, $this->autoMentors ) ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getWeightedAutoAssignedMentors(): array {
		return $this->getAutoAssignedMentors();
	}

	/**
	 * @inheritDoc
	 */
	public function getManuallyAssignedMentors(): array {
		return array_unique( array_values( array_map( static function ( Mentor $mentor ) {
			return $mentor->getUserIdentity()->getName();
		}, $this->manualMentors ) ) );
	}
}
