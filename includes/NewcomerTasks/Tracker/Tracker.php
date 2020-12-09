<?php

namespace GrowthExperiments\NewcomerTasks\Tracker;

use Psr\Log\LoggerInterface;
use Status;
use Title;
use TitleFactory;

class Tracker {

	/** @var CacheStorage */
	private $storage;

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
	 * @param TitleFactory $titleFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		CacheStorage $storage,
		TitleFactory $titleFactory,
		LoggerInterface $logger
	) {
		$this->storage = $storage;
		$this->titleFactory = $titleFactory;
		$this->logger = $logger;
		$this->title = null;
	}

	/**
	 * @param int $pageId
	 * @param string|null $clickId
	 * @return bool|Status
	 */
	public function track( int $pageId, string $clickId = null ) {
		$this->title = $this->titleFactory->newFromID( $pageId );
		if ( !$this->title ) {
			$errorMessage = 'Unable to create a Title from page ID {pageId}';
			$errorData = [ 'pageId' => $pageId ];
			$this->logger->error( $errorMessage, $errorData );
			return Status::newFatal(
				new \ApiRawMessage( $errorMessage, 'title-failure' ), $errorData
			);
		}
		$this->clickId = $clickId;
		return $this->storage->set( $pageId );
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
	 * @return array
	 */
	public function getTrackedPageIds() :array {
		return $this->storage->get();
	}

}
