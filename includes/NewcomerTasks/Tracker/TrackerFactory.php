<?php

namespace GrowthExperiments\NewcomerTasks\Tracker;

use BagOStuff;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use TitleFactory;

class TrackerFactory {

	/**
	 * @var BagOStuff
	 */
	private $objectStash;
	/**
	 * @var ConfigurationLoader
	 */
	private $configurationLoader;
	/**
	 * @var TitleFactory
	 */
	private $titleFactory;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var TaskType
	 */
	private $taskTypeOverride;

	/**
	 * @param BagOStuff $objectStash
	 * @param ConfigurationLoader $configurationLoader
	 * @param TitleFactory $titleFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		BagOStuff $objectStash,
		ConfigurationLoader $configurationLoader,
		TitleFactory $titleFactory,
		LoggerInterface $logger
	) {
		$this->objectStash = $objectStash;
		$this->configurationLoader = $configurationLoader;
		$this->titleFactory = $titleFactory;
		$this->logger = $logger;
	}

	/**
	 * @param UserIdentity $user
	 * @return Tracker
	 */
	public function getTracker( UserIdentity $user ): Tracker {
		return new Tracker(
			new CacheStorage(
				$this->objectStash,
				$user
			),
			$this->configurationLoader,
			$this->titleFactory,
			$this->logger
		);
	}

	/**
	 * Set the type of the task the user is currently doing. Used to pass the information
	 * to the RecentChange_save hook when submitting a task. Valid for the current request only.
	 * @param TaskType $taskType
	 * @return void
	 */
	public function setTaskTypeOverride( TaskType $taskType ): void {
		$this->taskTypeOverride = $taskType;
	}

	/**
	 * Get the type of the task the user is currently doing. Used to pass the information
	 * to the RecentChange_save hook when submitting a task. Valid for the current request only.
	 * @return TaskType|null
	 */
	public function getTaskTypeOverride(): ?TaskType {
		return $this->taskTypeOverride;
	}

}
