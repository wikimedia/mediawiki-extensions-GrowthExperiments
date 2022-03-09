<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\ObjectFactory\ObjectFactory;

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
		$this->logger = new NullLogger();
	}

	/** @inheritDoc */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
		$this->taskSuggesterFactory->setLogger( $logger );
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
			if ( $suggester instanceof LoggerAwareInterface ) {
				$suggester->setLogger( $this->logger );
			}
		}
		return $suggester;
	}

}
