<?php

namespace GrowthExperiments\NewcomerTasks\Tracker;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use Psr\Log\LoggerInterface;
use StatusValue;
use Title;
use TitleFactory;

class Tracker {

	/** @var CacheStorage */
	private $storage;

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var TitleFactory */
	private $titleFactory;

	/** @var LoggerInterface */
	private $logger;

	/** @var null|Title */
	private $title;

	/** @var string|null */
	private $clickId;

	/**
	 * @param CacheStorage $storage
	 * @param ConfigurationLoader $configurationLoader
	 * @param TitleFactory $titleFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		CacheStorage $storage,
		ConfigurationLoader $configurationLoader,
		TitleFactory $titleFactory,
		LoggerInterface $logger
	) {
		$this->storage = $storage;
		$this->configurationLoader = $configurationLoader;
		$this->titleFactory = $titleFactory;
		$this->logger = $logger;
		$this->title = null;
	}

	/**
	 * @param int $pageId
	 * @param string $taskTypeId
	 * @param string|null $clickId
	 * @return bool|StatusValue
	 */
	public function track( int $pageId, string $taskTypeId, string $clickId = null ) {
		$this->title = $this->titleFactory->newFromID( $pageId );
		if ( !$this->title ) {
			return $this->makeError( 'Unable to create a Title from page ID {pageId}', [
				'pageId' => $pageId,
				'taskTypeId' => $taskTypeId,
			] );
		}
		$taskType = $this->configurationLoader->getTaskTypes()[$taskTypeId] ?? null;
		if ( !$taskType ) {
			return $this->makeError( 'Invalid task type ID: {taskTypeId}', [
				'pageId' => $pageId,
				'taskTypeId' => $taskTypeId,
			] );
		}
		$this->clickId = $clickId;
		return $this->storage->set( $pageId, $taskType->getId() );
	}

	/**
	 * @param array $additionalQueryParams
	 * @return string
	 */
	public function getTitleUrl( $additionalQueryParams = [] ) :string {
		return $this->title->getLinkURL(
			array_merge( $additionalQueryParams, [ 'geclickid' => $this->clickId ] )
		);
	}

	/**
	 * @return int[]
	 */
	public function getTrackedPageIds() :array {
		return array_keys( $this->storage->get() );
	}

	/**
	 * Get the TaskType of the task associated with the page, or null if no task is associated
	 * (or the stored task type was invalid, e.g. task type configuration has changed since).
	 * @param int $pageId
	 * @return TaskType|null
	 */
	public function getTaskTypeForPage( int $pageId ): ?TaskType {
		$taskTypeId = $this->storage->get()[$pageId] ?? null;
		if ( !$taskTypeId ) {
			return null;
		}
		return $this->configurationLoader->getTaskTypes()[$taskTypeId] ?? null;
	}

	/**
	 * @param string $errorMessage
	 * @param array $errorData
	 * @return StatusValue
	 */
	private function makeError( string $errorMessage, array $errorData ): StatusValue {
		$this->logger->error( $errorMessage, $errorData );
		return StatusValue::newFatal(
			new \ApiRawMessage( $errorMessage, 'title-failure' ), $errorData
		);
	}

}
