<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MessageLocalizer;

abstract class AbstractStructuredMentorProvider extends MentorProvider {

	private UserIdentityLookup $userIdentityLookup;
	private MessageLocalizer $messageLocalizer;

	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		MessageLocalizer $messageLocalizer
	) {
		parent::__construct();

		$this->userIdentityLookup = $userIdentityLookup;
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * @inheritDoc
	 */
	public function getSignupTitle(): ?Title {
		return SpecialPage::getTitleFor( 'EnrollAsMentor' );
	}

	/**
	 * Get list of mentors
	 *
	 * @return array
	 */
	abstract protected function getMentorData(): array;

	/**
	 * @param UserIdentity $mentor
	 * @return array|null
	 */
	private function getMentorDataForUser( UserIdentity $mentor ): ?array {
		return $this->getMentorData()[$mentor->getId()] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function newMentorFromUserIdentity(
		UserIdentity $mentorUser,
		?UserIdentity $menteeUser = null
	): Mentor {
		return $this->newFromMentorDataAndUserIdentity(
			$this->getMentorDataForUser( $mentorUser ),
			$mentorUser,
			$menteeUser
		);
	}

	/**
	 * @param array|null $mentorData
	 * @param UserIdentity $mentorUser
	 * @param UserIdentity|null $menteeUser
	 * @return Mentor
	 */
	private function newFromMentorDataAndUserIdentity(
		?array $mentorData,
		UserIdentity $mentorUser,
		?UserIdentity $menteeUser = null
	): Mentor {
		$weight = $mentorData['weight'] ?? IMentorWeights::WEIGHT_NORMAL;
		if (
			$mentorData &&
			array_key_exists( 'automaticallyAssigned', $mentorData ) &&
			!$mentorData['automaticallyAssigned']
		) {
			// T347157: To aid with migration; remove once automaticallyAssigned is not set.
			$weight = IMentorWeights::WEIGHT_NONE;
		}
		return new Mentor(
			$mentorUser,
			$mentorData['message'] ?? null,
			$this->getDefaultMentorIntroText( $mentorUser, $menteeUser ),
			$weight
		);
	}

	/**
	 * @param UserIdentity $mentor
	 * @param ?UserIdentity $mentee
	 * @return string
	 */
	private function getDefaultMentorIntroText(
		UserIdentity $mentor,
		?UserIdentity $mentee
	): string {
		return $this->messageLocalizer
			->msg( 'growthexperiments-homepage-mentorship-intro' )
			->inContentLanguage()
			->params( $mentor->getName() )
			->params( $mentee ? $mentee->getName() : '' )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	public function getMentors(): array {
		$mentorIds = array_keys( $this->getMentorData() );
		if ( $mentorIds === [] ) {
			return [];
		}

		return $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $mentorIds )
			->registered()
			->caller( __METHOD__ )
			->fetchUserNames();
	}

	/**
	 * @inheritDoc
	 */
	public function getAutoAssignedMentors(): array {
		$userIDs = array_keys( array_filter(
			$this->getMentorData(),
			function ( array $mentorData, int $userId ) {
				return $this->newFromMentorDataAndUserIdentity(
					$mentorData,
					new UserIdentityValue( $userId, $mentorData['username'] ?? '' )
				)->getWeight() !== IMentorWeights::WEIGHT_NONE;
			},
			ARRAY_FILTER_USE_BOTH
		) );

		if ( $userIDs === [] ) {
			return [];
		}

		return $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIDs )
			->registered()
			->caller( __METHOD__ )
			->fetchUserNames();
	}

	/**
	 * @inheritDoc
	 */
	public function getWeightedAutoAssignedMentors(): array {
		$mentors = $this->getMentorData();

		$usernames = [];
		foreach ( $mentors as $userId => $mentorData ) {
			$user = $this->userIdentityLookup->getUserIdentityByUserId( $userId );
			if ( !$user ) {
				continue;
			}

			$mentor = $this->newFromMentorDataAndUserIdentity(
				$mentorData,
				$user
			);
			if ( $mentor->getWeight() === IMentorWeights::WEIGHT_NONE ) {
				continue;
			}

			$usernames = array_merge( $usernames, array_fill(
				0,
				$mentorData['weight'],
				$user->getName()
			) );
		}
		return $usernames;
	}

	/**
	 * @inheritDoc
	 */
	public function getManuallyAssignedMentors(): array {
		$userIDs = array_keys( array_filter(
			$this->getMentorData(),
			function ( array $mentorData, int $userId ) {
				return $this->newFromMentorDataAndUserIdentity(
					$mentorData,
					new UserIdentityValue( $userId, $mentorData['username'] ?? '' )
				)->getWeight() === IMentorWeights::WEIGHT_NONE;
			},
			ARRAY_FILTER_USE_BOTH
		) );

		if ( $userIDs === [] ) {
			return [];
		}

		return $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIDs )
			->registered()
			->caller( __METHOD__ )
			->fetchUserNames();
	}
}
