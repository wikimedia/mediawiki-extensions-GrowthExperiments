<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use IContextSource;
use Message;

/**
 * Describes a type of suggested edit.
 */
class TaskType {

	const DIFFICULTY_EASY = 'easy';
	const DIFFICULTY_MEDIUM = 'medium';
	const DIFFICULTY_HARD = 'hard';

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
		return $context->msg( 'growthexperiments-newcomertasks-tasktype-name-' . $this->getId() );
	}

	/**
	 * Description of the task type.
	 * @param IContextSource $context
	 * @return Message
	 */
	public function getDescription( IContextSource $context ): Message {
		return $context->msg( 'growthexperiments-newcomertasks-tasktype-description-'
			. $this->getId() );
	}

}
