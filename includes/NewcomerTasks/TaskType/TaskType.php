<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use Message;
use MessageLocalizer;

/**
 * Describes a type of suggested edit.
 */
class TaskType {

	public const DIFFICULTY_EASY = 'easy';
	public const DIFFICULTY_MEDIUM = 'medium';
	public const DIFFICULTY_HARD = 'hard';

	public const DIFFICULTY_CLASSES = [
		self::DIFFICULTY_EASY,
		self::DIFFICULTY_MEDIUM,
		self::DIFFICULTY_HARD,
	];

	/** @var string Task type ID, e.g. 'copyedit'. */
	protected $id;

	/** @var string TaskTypeHandler ID. */
	protected $handlerId;

	/** @var string Task type difficulty class, one of the DIFFICULTY_* constants. */
	protected $difficulty;

	/** @var string|null Page name to point the "learn more" link to. */
	protected $learnMoreLink;

	/**
	 * @param string $id Task type ID, e.g. 'copyedit'.
	 * @param string $difficulty One of the DIFFICULTY_* constants.
	 * @param array $extraData Optional pieces of information
	 *   - 'learnMoreLink' (string): Page title for the "learn more" link for this task type.
	 */
	public function __construct( $id, $difficulty, array $extraData = [] ) {
		$this->id = $id;
		$this->difficulty = $difficulty;
		$this->learnMoreLink = $extraData['learnMoreLink'] ?? null;
	}

	/**
	 * Task type ID, e.g. 'copyedit'.
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 * @internal for use by TaskTypeHandlerRegistry only
	 */
	public function getHandlerId() {
		return $this->handlerId;
	}

	/**
	 * @param string $handlerId
	 * @internal for use by TaskTypeHandlerRegistry only
	 */
	public function setHandlerId( $handlerId ) {
		$this->handlerId = $handlerId;
	}

	/**
	 * One of the DIFFICULTY_* constants.
	 * @return string
	 */
	public function getDifficulty() {
		return $this->difficulty;
	}

	/**
	 * Page title for the "learn more" link for this task type.
	 * @return string|null
	 */
	public function getLearnMoreLink() {
		return $this->learnMoreLink;
	}

	/**
	 * Human-readable name of the task type.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getName( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-tasktype-name-'
			. $this->getId() );
	}

	/**
	 * Description of the task type.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getDescription( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-tasktype-description-'
			. $this->getId() );
	}

	/**
	 * Short description of the task type.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getShortDescription( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg(
			'growthexperiments-homepage-suggestededits-tasktype-shortdescription-' . $this->getId() );
	}

	/**
	 * Label for the task type; typically either the name, or a combination of the name and
	 * the short description.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getLabel( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg(
			'growthexperiments-homepage-suggestededits-tasktype-label-' . $this->getId() );
	}

	/**
	 * Time estimate for the task type.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getTimeEstimate( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-tasktype-time-'
			. $this->getId() );
	}

	/**
	 * Return an array (JSON-ish) representation of the task type.
	 * @param MessageLocalizer $messageLocalizer
	 * @return array
	 */
	public function toArray( MessageLocalizer $messageLocalizer ) {
		return [
			'id' => $this->getId(),
			'difficulty' => $this->getDifficulty(),
			'messages' => [
				'name' => $this->getName( $messageLocalizer )->text(),
				'description' => $this->getDescription( $messageLocalizer )->text(),
				'shortdescription' => $this->getShortDescription( $messageLocalizer )->text(),
				'label' => $this->getLabel( $messageLocalizer )->text(),
				'timeestimate' => $this->getTimeEstimate( $messageLocalizer )->text(),
			],
			'learnMoreLink' => $this->getLearnMoreLink(),
		];
	}

}
