<?php

namespace GrowthExperiments\Mentorship\Provider;

use GrowthExperiments\Config\Validation\StructuredMentorListValidator;
use GrowthExperiments\Config\WikiPageConfigLoader;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\Mentorship\Mentor;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
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

	/** @var WikiPageConfigWriterFactory */
	private $configWriterFactory;

	/** @var StructuredMentorListValidator */
	private $mentorListValidator;

	/**
	 * @param WikiPageConfigLoader $configLoader
	 * @param WikiPageConfigWriterFactory $configWriterFactory
	 * @param StructuredMentorListValidator $mentorListValidator
	 * @param LinkTarget $mentorList
	 */
	public function __construct(
		WikiPageConfigLoader $configLoader,
		WikiPageConfigWriterFactory $configWriterFactory,
		StructuredMentorListValidator $mentorListValidator,
		LinkTarget $mentorList
	) {
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
			'automaticallyAssigned' => $mentor->getAutoAssigned(),
		];
	}

	/**
	 * Wrapper around WikiPageConfigWriter to save all mentor data
	 *
	 * @param array $mentorData
	 * @param string $summary
	 * @param UserIdentity $performer
	 * @return StatusValue
	 */
	private function saveMentorData(
		array $mentorData,
		string $summary,
		UserIdentity $performer
	): StatusValue {
		$configWriter = $this->configWriterFactory
			->newWikiPageConfigWriter( $this->mentorList, $performer );
		$configWriter->setVariable( self::CONFIG_KEY, $mentorData );
		return $configWriter->save( $summary, false, self::CHANGE_TAG );
	}

	/**
	 * @inheritDoc
	 */
	public function addMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary
	): StatusValue {
		$mentorUserIdentity = $mentor->getUserIdentity();
		if ( !$mentorUserIdentity->isRegistered() ) {
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

		return $this->saveMentorData( $mentorData, $summary, $performer );
	}

	/**
	 * @inheritDoc
	 */
	public function removeMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary
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

		return $this->saveMentorData( $mentorData, $summary, $performer );
	}

	/**
	 * @inheritDoc
	 */
	public function changeMentor(
		Mentor $mentor,
		UserIdentity $performer,
		string $summary
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

		return $this->saveMentorData( $mentorData, $summary, $performer );
	}
}
