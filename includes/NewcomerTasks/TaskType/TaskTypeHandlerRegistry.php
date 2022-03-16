<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use InvalidArgumentException;
use OutOfBoundsException;
use Wikimedia\ObjectFactory\ObjectFactory;

/**
 * Keeps track of TaskTypeHandlers.
 */
class TaskTypeHandlerRegistry {

	/** @var ObjectFactory */
	private $objectFactory;

	/** @var (array|callable)[] ObjectFactory specifications or callbacks, keyed by handler ID. */
	private $handlerSpecifications;

	/** @var TaskTypeHandler[] Task type handlers, keyed by ID. */
	private $handlers = [];

	/**
	 * @param ObjectFactory $objectFactory
	 * @param array $handlerSpecifications ObjectFactory specifications or callbacks,
	 *   keyed by handler ID.
	 */
	public function __construct( ObjectFactory $objectFactory, array $handlerSpecifications = [] ) {
		$this->objectFactory = $objectFactory;
		$this->handlerSpecifications = $handlerSpecifications;
	}

	/**
	 * @param string $handlerId TaskTypeHandler ID
	 * @return bool
	 */
	public function has( string $handlerId ): bool {
		return array_key_exists( $handlerId, $this->handlers )
			|| array_key_exists( $handlerId, $this->handlerSpecifications );
	}

	/**
	 * @param string $handlerId TaskTypeHandler ID
	 * @return TaskTypeHandler
	 * @throws OutOfBoundsException when invalid $handlerId is provided
	 */
	public function get( string $handlerId ): TaskTypeHandler {
		return $this->handlers[$handlerId] ?? $this->createHandler( $handlerId );
	}

	/**
	 * @param TaskType $taskType
	 * @return TaskTypeHandler
	 */
	public function getByTaskType( TaskType $taskType ): TaskTypeHandler {
		return $this->get( $taskType->getHandlerId() );
	}

	/**
	 * Returns a list of all handle TaskTypeHandler IDs that would be accepted by get().
	 * @return array
	 */
	public function getKnownIds(): array {
		$knownIds = array_keys( $this->handlerSpecifications );
		sort( $knownIds );
		return $knownIds;
	}

	/**
	 * @param string $handlerId
	 * @param array|callable $spec
	 * @throws InvalidArgumentException When a handler is already registered for the given ID.
	 */
	public function register( string $handlerId, $spec ): void {
		if ( array_key_exists( $handlerId, $this->handlerSpecifications ) ) {
			throw new InvalidArgumentException( 'A task type handler is already registered for the ID '
				. $handlerId );
		}
		$this->handlerSpecifications[$handlerId] = $spec;
	}

	/**
	 * Gets all the edit tags defined by all the possible task types.
	 * @return string[]
	 */
	public function getChangeTags(): array {
		$changeTags = [];
		foreach ( $this->getKnownIds() as $handlerId ) {
			$handler = $this->get( $handlerId );
			$changeTags = array_merge( $changeTags, $handler->getChangeTags() );
		}
		return array_unique( $changeTags );
	}

	/**
	 * @param string $handlerId
	 * @return TaskTypeHandler
	 * @throws OutOfBoundsException When there is no handler registered for the given ID.
	 */
	private function createHandler( string $handlerId ): TaskTypeHandler {
		$spec = $this->handlerSpecifications[$handlerId] ?? null;
		if ( !$spec ) {
			throw new OutOfBoundsException( 'No task type handler registered for the ID ' . $handlerId );
		}
		$handler = $this->objectFactory->createObject( $spec, [
			'assertClass' => TaskTypeHandler::class,
			'allowCallable' => true,
		] );
		/** @var TaskTypeHandler $handler */'@phan-var TaskTypeHandler $handler';
		$this->handlers[$handlerId] = $handler;
		return $handler;
	}

}
