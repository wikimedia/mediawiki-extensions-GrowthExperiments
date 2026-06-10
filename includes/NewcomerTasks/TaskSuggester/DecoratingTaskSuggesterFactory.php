<?php

namespace GrowthExperiments\NewcomerTasks\TaskSuggester;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
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
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		TaskSuggesterFactory $taskSuggesterFactory,
		ObjectFactory $objectFactory,
		array $decorators,
		LoggerInterface $logger
	) {
		parent::__construct( $logger );
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->objectFactory = $objectFactory;
		$this->decorators = $decorators;
	}

	/** @inheritDoc */
	public function create( ?ConfigurationLoader $customConfigurationLoader = null ) {
		$suggester = $this->taskSuggesterFactory->create( $customConfigurationLoader );
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
