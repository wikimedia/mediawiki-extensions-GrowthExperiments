<?php

namespace GrowthExperiments\NewcomerTasks\Task;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use Countable;
use IteratorAggregate;
use MediaWiki\Json\JsonUnserializable;
use MediaWiki\Json\JsonUnserializableTrait;
use MediaWiki\Json\JsonUnserializer;
use MediaWiki\Page\ProperPageIdentity;
use OutOfBoundsException;
use Title;
use Traversable;
use Wikimedia\Assert\Assert;

/**
 * A list of task suggestions, which constitute a slice of the total result set of suggestions.
 * Used as a convenience class for queries with limit/offset to pass along some metadata
 * about the full result set (such as offset or total result count).
 */
class TaskSet implements IteratorAggregate, Countable, ArrayAccess, JsonUnserializable {

	use JsonUnserializableTrait;

	/** @var Task[] */
	private $tasks;

	/** @var int Size of the full result set (can be larger than the size of this result set). */
	private $totalCount;

	/** @var int Offset within the full result set. */
	private $offset;

	/** @var array Arbitrary non-task-specific debug data */
	private $debugData = [];

	/** @var TaskSetFilters The task and topic filters used to generate this task set. */
	private $filters;

	/** @var array */
	private $qualityGateConfig = [];

	/** @var array Invalid tasks that are part of this task set. */
	private $invalidTasks = [];

	/**
	 * @param Task[] $tasks
	 * @param int $totalCount Size of the full result set
	 *   (can be larger than the size of this result set).
	 * @param int $offset Offset within the full result set.
	 * @param TaskSetFilters $filters
	 * @param Task[] $invalidTasks Tasks that were part of the TaskSet, but are not considered valid.
	 */
	public function __construct(
		array $tasks, $totalCount, $offset, TaskSetFilters $filters, array $invalidTasks = []
	) {
		Assert::parameterElementType( Task::class, $tasks, '$tasks' );
		$this->tasks = array_values( $tasks );
		$this->invalidTasks = array_values( $invalidTasks );
		$this->totalCount = $totalCount;
		$this->offset = $offset;
		$this->filters = $filters;
	}

	/**
	 * @inheritDoc
	 * @phan-suppress-next-line PhanTypeMismatchDeclaredReturn
	 * @return Traversable|Task[]
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->tasks );
	}

	/** @inheritDoc */
	public function count(): int {
		return count( $this->tasks );
	}

	/** @inheritDoc */
	public function offsetExists( $offset ): bool {
		return array_key_exists( $offset, $this->tasks );
	}

	/**
	 * @param int $offset
	 * @return Task
	 */
	public function offsetGet( $offset ): Task {
		if ( !array_key_exists( $offset, $this->tasks ) ) {
			throw new OutOfBoundsException( "TaskSet does not have item $offset; max offset: "
				. ( count( $this->tasks ) - 1 ) );
		}
		return $this->tasks[$offset];
	}

	/**
	 * This method cannot be used.
	 * @param mixed $offset
	 * @param mixed $value
	 * @suppress PhanPluginNeverReturnMethod LSP violation
	 */
	public function offsetSet( $offset, $value ): void {
		throw new BadMethodCallException( 'TaskSet is read-only' );
	}

	/**
	 * This method cannot be used.
	 * @param mixed $offset
	 * @suppress PhanPluginNeverReturnMethod LSP violation
	 */
	public function offsetUnset( $offset ): void {
		throw new BadMethodCallException( 'TaskSet is read-only' );
	}

	/**
	 * Size of the full result set (can be larger than the size of this result set), minus any invalidated
	 * tasks in the task set.
	 *
	 * In other words, getTotalCount is the number of suggestions matching some set of conditions
	 * while the suggestions returned by iterating the TaskSet are the result of
	 * further restricting that set with some limit/offset.
	 * @return int
	 */
	public function getTotalCount() {
		return $this->totalCount - count( $this->invalidTasks );
	}

	/**
	 * Offset within the full result set.
	 * @return int
	 */
	public function getOffset() {
		return $this->offset;
	}

	/**
	 * Get arbitrary non-task-specific debug data.
	 * @return array
	 */
	public function getDebugData(): array {
		return $this->debugData;
	}

	/**
	 * Set arbitrary non-task-specific debug data.
	 * @param array $debugData
	 */
	public function setDebugData( array $debugData ): void {
		$this->debugData = $debugData;
	}

	/**
	 * @return TaskSetFilters
	 */
	public function getFilters(): TaskSetFilters {
		return $this->filters;
	}

	/**
	 * Truncate the set of tasks.
	 *
	 * @param int $limit
	 */
	public function truncate( int $limit ): void {
		if ( $this->count() ) {
			$this->tasks = array_slice( $this->tasks, 0, $limit, true );
		}
	}

	/**
	 * Shuffle the tasks.
	 */
	public function randomSort(): void {
		shuffle( $this->tasks );
	}

	/**
	 * Compare this TaskSet's filters with another set of filters.
	 * @param TaskSetFilters $filters
	 * @return bool
	 */
	public function filtersEqual( TaskSetFilters $filters ): bool {
		return json_encode( $this->filters ) === json_encode( $filters );
	}

	/**
	 * An array of data to be used in controllers (for now, just client-side in QualityGate.js) for enforcing
	 * quality gates.
	 *
	 * @see modules/ext.growthExperiments.Homepage.SuggestedEdits/QualityGate.js
	 * @return array Keys are task type IDs, values are arbitrary data to be used by controllers.
	 */
	public function getQualityGateConfig(): array {
		return $this->qualityGateConfig;
	}

	/**
	 * @param string $taskTypeId
	 * @param array $config
	 */
	public function setQualityGateConfigForTaskType( string $taskTypeId, array $config ): void {
		$this->qualityGateConfig[$taskTypeId] = $config;
	}

	/**
	 * Set the quality gate data for a TaskSet. Useful when constructing a new TaskSet from an old one.
	 *
	 * @param array $config
	 */
	public function setQualityGateConfig( array $config ): void {
		$this->qualityGateConfig = $config;
	}

	/**
	 * @return Task[]
	 */
	public function getInvalidTasks(): array {
		return $this->invalidTasks;
	}

	/** @inheritDoc */
	protected function toJsonArray(): array {
		# T312589 explicitly calling jsonSerialize() will be unnecessary
		# in the future.
		return [
			'tasks' => array_map( static function ( Task $task ) {
				return $task->jsonSerialize();
			}, $this->tasks ),
			'invalidTasks' => array_map( static function ( Task $task ) {
				return $task->jsonSerialize();
			}, $this->invalidTasks ),
			'totalCount' => $this->totalCount,
			'offset' => $this->offset,
			'filters' => $this->filters->jsonSerialize(),
			'qualityGateConfig' => $this->qualityGateConfig,
			// debug data is not worth serializing
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonUnserializer $unserializer, array $json ) {
		# T312589: In the future JsonCodec will take care of unserializing
		# the values in the $json array itself.
		$tasks = array_map( static function ( $task ) use ( $unserializer ) {
			return $task instanceof Task ? $task :
				$unserializer->unserialize( $task, Task::class );
		}, $json['tasks'] );
		$invalidTasks = array_map( static function ( $task ) use ( $unserializer ) {
			return $task instanceof Task ? $task :
				$unserializer->unserialize( $task, Task::class );
		}, $json['invalidTasks'] );
		$filters = $json['filters'] instanceof TaskSetFilters ?
				 $json['filters'] :
				 $unserializer->unserialize( $json['filters'], TaskSetFilters::class );
		$taskSet = new self( $tasks, $json['totalCount'], $json['offset'], $filters, $invalidTasks );
		$taskSet->setQualityGateConfig( $json['qualityGateConfig'] );
		return $taskSet;
	}

	/**
	 * Check whether the task set contains a task for the specified page
	 *
	 * @param ProperPageIdentity $page
	 * @return bool
	 */
	public function containsPage( ProperPageIdentity $page ): bool {
		foreach ( $this->tasks as $task ) {
			if ( Title::newFromLinkTarget( $task->getTitle() )->isSamePageAs( $page ) ) {
				return true;
			}
		}
		return false;
	}

}
