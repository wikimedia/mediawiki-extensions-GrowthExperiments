<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use CirrusSearch\CirrusSearch;
use GrowthExperiments\NewcomerTasks\RecommendationSubmissionHandler;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use ManualLogEntry;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;
use StatusValue;

/**
 * Record the user's decision on the recommendations for a given page.
 */
class AddImageSubmissionHandler implements RecommendationSubmissionHandler {

	/** @var callable */
	private $cirrusSearchFactory;

	/**
	 * @param callable $cirrusSearchFactory A factory method returning a CirrusSearch instance.
	 */
	public function __construct(
		callable $cirrusSearchFactory
	) {
		$this->cirrusSearchFactory = $cirrusSearchFactory;
	}

	/** @inheritDoc */
	public function validate(
		ProperPageIdentity $page, UserIdentity $user, int $baseRevId, array $data
	): ?array {
		return null;
	}

	/** @inheritDoc */
	public function handle(
		ProperPageIdentity $page, UserIdentity $user, int $baseRevId, ?int $editRevId, array $data
	): StatusValue {
		if ( !array_key_exists( 'accepted', $data ) ) {
			return StatusValue::newFatal( 'growthexperiments-addimage-handler-accepted-missing' );
		} elseif ( !is_bool( $data['accepted'] ) ) {
			$type = gettype( $data['accepted'] );
			return StatusValue::newFatal( 'growthexperiments-addimage-handler-accepted-wrongtype', $type );
		}
		/** @var CirrusSearch $cirrusSearch */
		$cirrusSearch = ( $this->cirrusSearchFactory )();
		$cirrusSearch->resetWeightedTags( $page,
			ImageRecommendationTaskTypeHandler::WEIGHTED_TAG_PREFIX );

		$logEntry = new ManualLogEntry( 'growthexperiments', 'addimage' );
		$logEntry->setTarget( $page );
		$logEntry->setPerformer( $user );
		$logEntry->setParameters( [
			'accepted' => (bool)$data['accepted'],
		] );
		if ( $editRevId ) {
			$logEntry->setAssociatedRevId( $editRevId );
		}
		$logId = $logEntry->insert();
		// Do not publish to recent changes, it would be pointless as this action cannot
		// be inspected or patrolled.
		$logEntry->publish( $logId, 'udp' );
		return StatusValue::newGood( [ 'logId' => $logId ] );
	}

}
