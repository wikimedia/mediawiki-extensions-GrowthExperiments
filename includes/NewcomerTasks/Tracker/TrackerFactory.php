<?php

namespace GrowthExperiments\NewcomerTasks\Tracker;

use BagOStuff;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use TitleFactory;

class TrackerFactory {

	/**
	 * @var BagOStuff
	 */
	private $objectStash;
	/**
	 * @var TitleFactory
	 */
	private $titleFactory;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * TrackerFactory constructor.
	 * @param BagOStuff $objectStash
	 * @param TitleFactory $titleFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		BagOStuff $objectStash, TitleFactory $titleFactory, LoggerInterface $logger
	) {
		$this->objectStash = $objectStash;
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
			$this->titleFactory,
			$this->logger
		);
	}

}
