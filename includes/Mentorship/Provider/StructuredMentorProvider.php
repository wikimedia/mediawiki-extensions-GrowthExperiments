<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;
use SpecialPage;

class StructuredMentorProvider extends MentorProvider {
	use GetMentorDataTrait;

	private UserIdentityLookup $userIdentityLookup;
	private UserNameUtils $userNameUtils;
	private MessageLocalizer $messageLocalizer;

	/**
	 * @param WikiPageConfigLoader $configLoader
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserNameUtils $userNameUtils
	 * @param MessageLocalizer $messageLocalizer
	 * @param Title $mentorList
	 */
	public function __construct(
		WikiPageConfigLoader $configLoader,
		UserIdentityLookup $userIdentityLookup,
		UserNameUtils $userNameUtils,
		MessageLocalizer $messageLocalizer,
		Title $mentorList
	) {
		parent::__construct();

		$this->configLoader = $configLoader;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userNameUtils = $userNameUtils;
		$this->messageLocalizer = $messageLocalizer;
		$this->mentorList = $mentorList;
	}

	/**
	 * @inheritDoc
	 */
	public function getSignupTitle(): ?Title {
		return SpecialPage::getTitleFor( 'EnrollAsMentor' );
	}

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
			->fetchUserNames();
	}

	/**
	 * @inheritDoc
	 */
	public function getMentorsSafe(): array {
		return $this->getMentors();
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
			->fetchUserNames();
	}
}
