<?php

namespace GrowthExperiments\NewcomerTasks\AddImage\EventBus;

use Exception;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\EventBus\EventBusFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Utils\MWTimestamp;
use MediaWiki\WikiMap\WikiMap;

/**
 * Create and send event to EventGate when image suggestions are accepted, rejected or invalidated.
 */
class EventGateImageSuggestionFeedbackUpdater {

	private const STREAM = 'mediawiki.image_suggestions_feedback';
	private const STREAM_VERSION = '2.1.0';
	private const SCHEMA = '/mediawiki/page/image-suggestions-feedback/' . self::STREAM_VERSION;

	private EventBusFactory $eventBusFactory;
	private WikiPageFactory $wikiPageFactory;

	public function __construct( EventBusFactory $eventBusFactory, WikiPageFactory $wikiPageFactory ) {
		$this->eventBusFactory = $eventBusFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Create an event and send it via EventBus.
	 *
	 * @param int $articleId The article ID associated with the image recommendation.
	 * @param int $userId The user ID performing the accept/reject/invalidate action.
	 * @param bool|null $accepted True if accepted, false if rejected, null if invalidating for
	 * other reasons (e.g. image exists on page when user visits it)
	 * @param string $filename The filename, without the File: prefix
	 * @param string|null $sectionTitle Title of the section the suggestion is for
	 * @param int|null $sectionNumber Number of the section the suggestion is for
	 * @param array|null $rejectionReasons List of rejection reasons. See
	 *   AddImageSubmissionHandler::REJECTION_REASONS
	 * @throws Exception
	 */
	public function update(
		int $articleId,
		int $userId,
		?bool $accepted,
		string $filename,
		?string $sectionTitle,
		?int $sectionNumber,
		?array $rejectionReasons = []
	): void {
		$eventBus = $this->eventBusFactory->getInstanceForStream( self::STREAM );
		$eventFactory = $eventBus->getFactory();
		$attrs = [
			'wiki' => WikiMap::getCurrentWikiId(),
			'page_id' => $articleId,
			'filename' => $filename,
			'user_id' => $userId,
			'is_accepted' => $accepted === true,
			'is_rejected' => $accepted === false,
			'dt' => MWTimestamp::now( TS_ISO_8601 ),
			'origin_wiki' => 'commonswiki',
		];
		if ( $rejectionReasons ) {
			$attrs['rejection_reasons'] = $rejectionReasons;
		}
		if ( $sectionTitle !== null ) {
			$attrs['section_title'] = $sectionTitle;
		}
		if ( $sectionNumber !== null ) {
			$attrs['section_ordinal'] = $sectionNumber;
		}
		$event = $eventFactory->createEvent(
			$this->wikiPageFactory->newFromID( $articleId )->getTitle()->getCanonicalURL(),
			self::SCHEMA,
			self::STREAM,
			$attrs
		);
		DeferredUpdates::addCallableUpdate( static fn () => $eventBus->send( [ $event ] ) );
	}

}
