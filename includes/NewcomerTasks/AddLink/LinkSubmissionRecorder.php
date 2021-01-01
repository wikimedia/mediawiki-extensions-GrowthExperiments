<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use MediaWiki\User\UserIdentity;
use StatusValue;

/**
 * Record information about a user accepting/rejecting parts of a link recommendation.
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
		// TODO no-op for now
		return StatusValue::newGood();
	}

}
