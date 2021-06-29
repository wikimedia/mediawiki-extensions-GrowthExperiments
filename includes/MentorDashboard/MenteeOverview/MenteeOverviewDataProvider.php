<?php

namespace GrowthExperiments\MentorDashboard\MenteeOverview;

use MediaWiki\User\UserIdentity;

/**
 * Data provider for the mentor dashboard
 *
 * Implementations of this interface are to be used
 * by the mentee overview module directly.
 */
interface MenteeOverviewDataProvider {
	/**
	 * Generate data for the mentor, to show in mentee overview module
	 *
	 * Formatted as an array of associative arrays representing the mentees.
	 * The associative arrays have the following attributes for each mentee:
	 *    * username – username of the mentee
	 *    * user_id – user ID of the mentee
	 *    * editcount – total number of edits
	 *    * reverted – number of reverted edits
	 *    * blocks – number of blocks placed against the
	 *    * questions – number of questions they asked via Growth features
	 *    * registration – registration timestamp as stored in user_registration in user table;
	 *      may be missing or null for very old users.
	 *
	 * @param UserIdentity $mentor
	 * @return array[]
	 */
	public function getFormattedDataForMentor( UserIdentity $mentor ): array;
}
