<?php

declare( strict_types = 1 );

namespace GrowthExperiments\ReadingRecommendations;

use MediaWiki\User\UserIdentity;

/**
 * Computes a user's daily reading recommendations.
 *
 * Placeholder: returns no recommendations yet. General recommendations from
 * the wiki's Featured articles come with T435521, interest-based ones with
 * T435522.
 */
class ReadingRecommendationsService {

	/**
	 * @param UserIdentity $user
	 * @return ReadingRecommendation[]
	 */
	public function getRecommendations( UserIdentity $user ): array {
		return [];
	}
}
