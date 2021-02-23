<?php

namespace GrowthExperiments\NewcomerTasks\AddLink\SearchIndexUpdater;

use MediaWiki\Extension\EventBus\EventBusFactory;
use MediaWiki\Revision\RevisionRecord;
use StatusValue;

/**
 * Updates the search index via EventGate. Used in Wikimedia production.
 */
class EventGateSearchIndexUpdater implements SearchIndexUpdater {

	private const STREAM = 'mediawiki.revision-recommendation-create';

	/** @var EventBusFactory */
	private $eventBusFactory;

	/**
	 * @param EventBusFactory $eventBusFactory
	 */
	public function __construct(
		EventBusFactory $eventBusFactory
	) {
		$this->eventBusFactory = $eventBusFactory;
	}

	/** @inheritDoc */
	public function update( RevisionRecord $revision ) {
		$eventBus = $this->eventBusFactory->getInstanceForStream( self::STREAM );
		$eventFactory = $eventBus->getFactory();
		$event = $eventFactory->createRecommendationCreateEvent( self::STREAM, 'link', $revision );
		$result = $eventBus->send( [ $event ] );

		$status = StatusValue::newGood();
		if ( $result !== true ) {
			foreach ( (array)$result as $error ) {
				$status->fatal( 'rawmessage', $error );
			}
		}
		return $status;
	}

}
