<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorshipSummaryCreator;
use IDBAccessObject;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerAwareTrait;
use StatusValue;

/**
 * This class writes to the structured mentor list and allows to add/remove
 * mentors from the structured mentor list.
 *
 * Use StructuredMentorProvider to read the mentor list.
 *
 * This class uses WikiPageConfigWriter under the hood.
 */
abstract class AbstractStructuredMentorWriter implements IMentorWriter {
	use LoggerAwareTrait;

	/**
	 * @var string Change tag to tag structured mentor list edits with
	 *
	 * @note Keep in sync with extension.json (GrowthMentorList provider of
	 * CommunityConfiguration).
	 */
	public const CHANGE_TAG = 'mentor list change';

	/** @var string */
	public const CONFIG_KEY = 'Mentors';

	protected MentorProvider $mentorProvider;
	protected UserIdentityLookup $userIdentityLookup;
	protected UserFactory $userFactory;

	/**
	 * @param MentorProvider $mentorProvider
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		MentorProvider $mentorProvider,
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory
	) {
		$this->mentorProvider = $mentorProvider;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userFactory = $userFactory;
	}

	/**
	 * Get list of mentors
	 *
	 * @return array
	 */
	abstract protected function getMentorData(): array;

	/**
	 * Serialize a Mentor object to an array
	 *
	 * @param Mentor $mentor
	 * @return array
	 */
	public static function serializeMentor( Mentor $mentor ): array {
		return [
			'message' => $mentor->hasCustomIntroText() ? $mentor->getIntroText() : null,
			'weight' => $mentor->getWeight(),
		];
	}

	abstract protected function doSaveMentorData(
		array $mentorData,
		string $summary,
		UserIdentity $performer,
		bool $bypassWarnings
	): StatusValue;

	/**
	 * Save mentor data
	 *
	 * @param array $mentorData
	 * @param string $summary
	 * @param UserIdentity $performer
	 * @param bool $bypassWarnings
	 * @return StatusValue
	 */
	protected function saveMentorData(
		array $mentorData,
		string $summary,
		UserIdentity $performer,
		bool $bypassWarnings
	): StatusValue {
		// check if $performer is not blocked from the mentor list page
		if ( $this->isBlocked( $performer, IDBAccessObject::READ_LATEST ) ) {
			return StatusValue::newFatal( 'growthexperiments-mentor-writer-error-blocked' );
		}

		// add 'username' key for readability (T331444)
		foreach ( $mentorData as $mentorId => $_ ) {
			$mentorUser = $this->userIdentityLookup->getUserIdentityByUserId( $mentorId );
			if ( !$mentorUser ) {
				$this->logger->warning( 'Mentor list contains an invalid user for ID {userId}', [
					'userId' => $mentorId,
				] );
				continue;
			}

			$mentorData[$mentorId]['username'] = $mentorUser->getName();
		}

		return $this->doSaveMentorData(
			$mentorData,
			$summary,
			$performer,
			$bypassWarnings
		);
	}

	/**
	 * @inheritDoc
	 */
	public function addMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary,
		bool $bypassWarnings = false
	): StatusValue {
		$mentorUserIdentity = $mentor->getUserIdentity();
		if ( !$mentorUserIdentity->isRegistered()
			|| !$this->userFactory->newFromUserIdentity( $mentorUserIdentity )->isNamed()
		) {
			return StatusValue::newFatal(
				'growthexperiments-mentor-writer-error-anonymous-user',
				$mentorUserIdentity->getName()
			);
		}

		$mentorData = $this->getMentorData();
		if ( array_key_exists( $mentorUserIdentity->getId(), $mentorData ) ) {
			// we're trying to add someone who's already added
			return StatusValue::newFatal(
				'growthexperiments-mentor-writer-error-already-added',
				$mentorUserIdentity->getName()
			);
		}
		$mentorData[$mentorUserIdentity->getId()] = $this->serializeMentor( $mentor );

		return $this->saveMentorData(
			$mentorData,
			MentorshipSummaryCreator::createAddSummary(
				$performer,
				$mentorUserIdentity,
				$summary
			),
			$performer,
			$bypassWarnings
		);
	}

	/**
	 * @inheritDoc
	 */
	public function removeMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary,
		bool $bypassWarnings = false
	): StatusValue {
		$mentorUserIdentity = $mentor->getUserIdentity();

		$mentorData = $this->getMentorData();
		if ( !array_key_exists( $mentorUserIdentity->getId(), $mentorData ) ) {
			// we're trying to remove someone who isn't added
			return StatusValue::newFatal(
				'growthexperiments-mentor-writer-error-not-in-the-list',
				$mentorUserIdentity->getName()
			);
		}
		unset( $mentorData[$mentorUserIdentity->getId()] );

		return $this->saveMentorData(
			$mentorData,
			MentorshipSummaryCreator::createRemoveSummary(
				$performer,
				$mentorUserIdentity,
				$summary
			),
			$performer,
			$bypassWarnings
		);
	}

	/**
	 * @inheritDoc
	 */
	public function changeMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary,
		bool $bypassWarnings = false
	): StatusValue {
		$mentorUserIdentity = $mentor->getUserIdentity();

		$mentorData = $this->getMentorData();
		if ( !array_key_exists( $mentorUserIdentity->getId(), $mentorData ) ) {
			// we're trying to change someone who isn't added
			return StatusValue::newFatal(
				'growthexperiments-mentor-writer-error-not-in-the-list',
				$mentorUserIdentity->getName()
			);
		}
		$mentorData[$mentorUserIdentity->getId()] = $this->serializeMentor( $mentor );

		return $this->saveMentorData(
			$mentorData,
			MentorshipSummaryCreator::createChangeSummary(
				$performer,
				$mentorUserIdentity,
				$summary
			),
			$performer,
			$bypassWarnings
		);
	}

	/**
	 * @inheritDoc
	 */
	public function touchList( UserIdentity $performer, string $summary ): StatusValue {
		$mentorData = $this->getMentorData();
		foreach ( $mentorData as $mentorId => $mentorArr ) {
			$mentorUser = $this->userIdentityLookup->getUserIdentityByUserId( $mentorId );
			if ( !$mentorUser ) {
				continue;
			}
			$mentorData[$mentorUser->getId()] = $this->serializeMentor(
				$this->mentorProvider->newMentorFromUserIdentity( $mentorUser )
			);
		}
		return $this->saveMentorData(
			$mentorData,
			$summary,
			$performer,
			true
		);
	}
}
