<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Mentorship\Mentor;
use InvalidArgumentException;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

/**
 * Static implementation of MentorProvider, created for use in tests
 */
class StaticMentorProvider extends MentorProvider {

	/** @var Mentor[] */
	private array $autoMentors;

	/** @var Mentor[] */
	private array $manualMentors;

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
	public function getMentors(): array {
		return array_unique(
			array_merge(
				$this->getAutoAssignedMentors(),
				$this->getManuallyAssignedMentors()
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getAutoAssignedMentors(): array {
		return array_unique( array_values( array_map( static function ( Mentor $mentor ) {
			return $mentor->getUserIdentity();
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
			return $mentor->getUserIdentity();
		}, $this->manualMentors ) ) );
	}
}
