<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use Wikimedia\ObjectFactory;

/**
 * A TaskSuggesterFactory that wraps another factory and decorates the suggester created by it.
 */
class DecoratingTaskSuggesterFactory extends TaskSuggesterFactory {

	/** @var TaskSuggesterFactory */
	private $taskSuggesterFactory;

	/** @var ObjectFactory */
	private $objectFactory;

	/**
	 * A list of ObjectFactory specifications for the decorators. The decorated suggester is
	 * passed via the 'extraArgs' option.
	 * @var array[]
	 */
	private $decorators;

	/**
	 * @param TaskSuggesterFactory $taskSuggesterFactory
	 * @param ObjectFactory $objectFactory
	 * @param array $decorators A list of ObjectFactory specifications for the decorators.
	 *   The decorated suggester is passed via the 'extraArgs' option.
	 */
	public function __construct(
		TaskSuggesterFactory $taskSuggesterFactory,
		ObjectFactory $objectFactory,
		array $decorators
	) {
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->objectFactory = $objectFactory;
		$this->decorators = $decorators;
	}

	/** @inheritDoc */
	public function create() {
		$suggester = $this->taskSuggesterFactory->create();
		foreach ( $this->decorators as $spec ) {
			$suggester = $this->objectFactory->createObject( $spec, [
				'allowCallable' => true,
				'extraArgs' => [ $suggester ],
				'assertClass' => TaskSuggester::class,
			] );
		}
		return $suggester;
	}

}
