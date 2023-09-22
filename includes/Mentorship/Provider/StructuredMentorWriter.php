<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Config\Validation\StructuredMentorListValidator;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\MentorshipSummaryCreator;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use StatusValue;

/**
 * This class writes to the structured mentor list and allows to add/remove
 * mentors from the structured mentor list.
 *
 * Use StructuredMentorProvider to read the mentor list.
 *
 * This class uses WikiPageConfigWriter under the hood.
 */
class StructuredMentorWriter implements IMentorWriter {
	use GetMentorDataTrait;

	/** @var string Change tag to tag structured mentor list edits with */
	public const CHANGE_TAG = 'mentor list change';

	/** @var string */
	public const CONFIG_KEY = 'Mentors';

	private UserIdentityLookup $userIdentityLookup;
	private UserFactory $userFactory;
	private WikiPageConfigWriterFactory $configWriterFactory;
	private StructuredMentorListValidator $mentorListValidator;

	/**
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param UserFactory $userFactory
	 * @param WikiPageConfigLoader $configLoader
	 * @param WikiPageConfigWriterFactory $configWriterFactory
	 * @param StructuredMentorListValidator $mentorListValidator
	 * @param Title $mentorList
	 */
	public function __construct(
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory,
		WikiPageConfigLoader $configLoader,
		WikiPageConfigWriterFactory $configWriterFactory,
		StructuredMentorListValidator $mentorListValidator,
		Title $mentorList
	) {
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userFactory = $userFactory;
		$this->configLoader = $configLoader;
		$this->configWriterFactory = $configWriterFactory;
		$this->mentorListValidator = $mentorListValidator;
		$this->mentorList = $mentorList;
	}

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

	/**
	 * Wrapper around WikiPageConfigWriter to save all mentor data
	 *
	 * @param array $mentorData
	 * @param string $summary
	 * @param UserIdentity $performer
	 * @param bool $bypassWarnings Should warnings raised by the validator stop the operation?
	 * @return StatusValue
	 */
	private function saveMentorData(
		array $mentorData,
		string $summary,
		UserIdentity $performer,
		bool $bypassWarnings
	): StatusValue {
		// check if $performer is not blocked from the mentor list page
		if ( $this->isBlocked( $performer, Authority::READ_LATEST ) ) {
			return StatusValue::newFatal( 'growthexperiments-mentor-writer-error-blocked' );
		}

		// add 'username' key for readability (T331444)
		foreach ( $mentorData as $mentorId => $_ ) {
			$mentorUser = $this->userIdentityLookup->getUserIdentityByUserId( $mentorId );
			if ( !$mentorUser ) {
				$this->logger->warning( $this->mentorList->getText() . ' contains an invalid user for ID {userId}', [
					'userId' => $mentorId,
					'namespace' => $this->mentorList->getNamespace(),
					'title' => $this->mentorList->getText()
				] );
				continue;
			}

			$mentorData[$mentorId]['username'] = $mentorUser->getName();
		}

		$configWriter = $this->configWriterFactory
			->newWikiPageConfigWriter( $this->mentorList, $performer );
		$configWriter->setVariable( self::CONFIG_KEY, $mentorData );
		return $configWriter->save( $summary, false, self::CHANGE_TAG, $bypassWarnings );
	}

	/**
	 * @inheritDoc
	 */
	public function isBlocked(
		UserIdentity $performer,
		int $freshness = Authority::READ_NORMAL
	): bool {
		$block = $this->userFactory->newFromUserIdentity( $performer )->getBlock( $freshness );
		return $block && $block->appliesToTitle( $this->mentorList );
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
}
