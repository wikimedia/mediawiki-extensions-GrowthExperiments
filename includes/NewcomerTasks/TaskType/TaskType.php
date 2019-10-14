<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use IContextSource;
use Message;

/**
 * Describes a type of suggested edit.
 */
class TaskType {

	public const DIFFICULTY_EASY = 'easy';
	public const DIFFICULTY_MEDIUM = 'medium';
	public const DIFFICULTY_HARD = 'hard';

	public static $difficultyClasses = [
		self::DIFFICULTY_EASY,
		self::DIFFICULTY_MEDIUM,
		self::DIFFICULTY_HARD,
	];

	/** @var string Task type ID, e.g. 'copyedit'. */
	protected $id;

	/** @var string Task type difficulty class, one of the DIFFICULTY_* constants. */
	protected $difficulty;

	/**
	 * @param string $id Task type ID, e.g. 'copyedit'.
	 * @param string $difficulty One of the DIFFICULTY_* constants.
	 */
	public function __construct( $id, $difficulty ) {
		$this->id = $id;
		$this->difficulty = $difficulty;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getDifficulty() {
		return $this->difficulty;
	}

	/**
	 * Human-readable name of the task type.
	 * @param IContextSource $context
	 * @return Message
	 */
	public function getName( IContextSource $context ): Message {
		return $context->msg( 'growthexperiments-homepage-suggestededits-tasktype-name-'
			. $this->getId() );
	}

	/**
	 * Description of the task type.
	 * @param IContextSource $context
	 * @return Message
	 */
	public function getDescription( IContextSource $context ): Message {
		return $context->msg( 'growthexperiments-homepage-suggestededits-tasktype-description-'
			. $this->getId() );
	}

	/**
	 * Short description of the task type.
	 * @param IContextSource $context
	 * @return Message
	 */
	public function getShortDescription( IContextSource $context ): Message {
		return $context->msg( 'growthexperiments-homepage-suggestededits-tasktype-shortdescription-'
		  . $this->getId() );
	}

	/**
	 * Time estimate for the task type.
	 * @param IContextSource $context
	 * @return Message
	 */
	public function getTimeEstimate( IContextSource $context ): Message {
		return $context->msg( 'growthexperiments-homepage-suggestededits-tasktype-time-'
		  . $this->getId() );
	}

}
