<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\StatusFormatter;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use MessageLocalizer;

class CommunityStructuredMentorProvider extends MentorProvider {
	use CommunityGetMentorDataTrait;

	private UserIdentityLookup $userIdentityLookup;
	private MessageLocalizer $messageLocalizer;

	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		MessageLocalizer $messageLocalizer,
		IConfigurationProvider $provider,
		StatusFormatter $statusFormatter
	) {
		parent::__construct();

		$this->userIdentityLookup = $userIdentityLookup;
		$this->messageLocalizer = $messageLocalizer;
		$this->provider = $provider;
		$this->statusFormatter = $statusFormatter;
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
	 * @param UserIdentity $mentor
	 * @return array|null
	 */
	private function getMentorDataForUser( UserIdentity $mentor ): ?array {
		return $this->getMentorData()[$mentor->getId()] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getSignupTitle(): ?Title {
		return SpecialPage::getTitleFor( 'EnrollAsMentor' );
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
	 * @inheritDoc
	 */
	public function getMentors(): array {
		$mentorIds = array_keys( $this->getMentorData() );
		if ( $mentorIds === [] ) {
			return [];
		}

		return iterator_to_array( $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $mentorIds )
			->registered()
			->caller( __METHOD__ )
			->fetchUserIdentities() );
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

		return iterator_to_array( $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIDs )
			->registered()
			->caller( __METHOD__ )
			->fetchUserIdentities() );
	}

	/**
	 * @inheritDoc
	 */
	public function getWeightedAutoAssignedMentors(): array {
		$mentorsData = $this->getMentorData();
		$result = [];
		foreach ( $mentorsData as $userId => $mentorData ) {
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

			$result = array_merge( $result, array_fill(
				0,
				$mentor->getWeight(),
				$user
			) );
		}
		return $result;
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

		return iterator_to_array( $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserIds( $userIDs )
			->registered()
			->caller( __METHOD__ )
			->fetchUserIdentities() );
	}
}
