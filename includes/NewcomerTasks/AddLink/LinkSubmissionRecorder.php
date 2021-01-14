<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use ManualLogEntry;
use MediaWiki\User\UserIdentity;
use StatusValue;

/**
 * Record information about a user accepting/rejecting parts of a link recommendation.
 * This involves creating a log entry and updating an article-specific exclusion list.
 */
class LinkSubmissionRecorder {

	/**
	 * Record the results of a user reviewing a link recommendation.
	 * @param UserIdentity $user
	 * @param LinkRecommendation $linkRecommendation
	 * @param string[] $acceptedTargets
	 * @param string[] $rejectedTargets
	 * @param string[] $skippedTargets
	 * @return StatusValue
	 */
	public function record(
		UserIdentity $user,
		LinkRecommendation $linkRecommendation,
		array $acceptedTargets,
		array $rejectedTargets,
		array $skippedTargets
	): StatusValue {
		$logId = $this->log( $user, $linkRecommendation,
			count( $acceptedTargets ) + count( $rejectedTargets ) + count( $skippedTargets ) );
		return StatusValue::newGood( [ 'logId' => $logId ] );
	}

	/**
	 * Make a Special:Log entry.
	 * @param UserIdentity $user
	 * @param LinkRecommendation $linkRecommendation
	 * @param int $linkCount Number of reviewed links.
	 * @return int Log ID.
	 */
	private function log(
		UserIdentity $user,
		LinkRecommendation $linkRecommendation,
		int $linkCount
	): int {
		$logEntry = new ManualLogEntry( 'growthexperiments', 'addlink' );
		$logEntry->setTarget( $linkRecommendation->getTitle() );
		$logEntry->setPerformer( $user );
		$logEntry->setParameters( [ '4:number:count' => $linkCount ] );
		$logId = $logEntry->insert();
		// Do not publish to recent changes, it would be pointless as this action cannot
		// be inspected or patrolled.
		$logEntry->publish( $logId, 'udp' );
		return $logId;
	}

}
