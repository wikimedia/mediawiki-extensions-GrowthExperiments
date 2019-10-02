<?php

namespace GrowthExperiments\NewcomerTasks;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use Wikimedia\Assert\Assert;

/**
 * A list of task suggestions, which constitute a slice of the total result set of suggestions.
 * Used as a convenience class for queries with limit/offset to pass along some metadata
 * about the full result set (such as offset or total result count).
 */
class TaskSet implements IteratorAggregate, Countable {

	/** @var Task[] */
	private $tasks;

	/** @var int Size of the full result set (can be larger than the size of this result set). */
	private $totalCount;

	/** @var int Offset within the full result set. */
	private $offset;

	/**
	 * @param Task[] $tasks
	 * @param int $totalCount Size of the full result set
	 *   (can be larger than the size of this result set).
	 * @param int $offset Offset within the full result set.
	 */
	public function __construct( array $tasks, $totalCount, $offset ) {
		Assert::parameterElementType( Task::class, $tasks, '$tasks' );
		$this->tasks = array_values( $tasks );
		$this->totalCount = $totalCount;
		$this->offset = $offset;
	}

	/** @inheritDoc */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->tasks );
	}

	/** @inheritDoc */
	public function count() {
		return count( $this->tasks );
	}

	/**
	 * Size of the full result set (can be larger than the size of this result set).
	 * In other words, getTotalCount is the number of suggestions matching some set of conditions
	 * while the suggestions returned by iterating the TaskSet are the result of
	 * further restricting that set with some limit/offset.
	 * @return int
	 */
	public function getTotalCount() {
		return $this->totalCount;
	}

	/**
	 * Offset within the full result set.
	 * @return int
	 */
	public function getOffset() {
		return $this->offset;
	}

}
