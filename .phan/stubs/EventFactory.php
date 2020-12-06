<?php

namespace MediaWiki\Extension\EventBus;

use MediaWiki\Revision\RevisionRecord;

class EventFactory {

	/**
	 * @param $stream
	 * @param $recommendationType
	 * @param RevisionRecord $revisionRecord
	 * @return array
	 */
	public function createRecommendationCreateEvent(
		$stream, $recommendationType, RevisionRecord $revisionRecord
	) {}

}
