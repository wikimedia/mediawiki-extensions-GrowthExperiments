<?php

namespace GrowthExperiments\NewcomerTasks\Tracker;

use BagOStuff;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
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

}
