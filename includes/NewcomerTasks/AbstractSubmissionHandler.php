<?php

namespace GrowthExperiments\NewcomerTasks;

use MediaWiki\User\UserIdentity;

abstract class AbstractSubmissionHandler {

	/**
	 * Return the message key for the error message if the user is unregistered,
	 * otherwise return null.
	 *
	 * @param UserIdentity $user
	 * @return string|null
	 */
	public function getUserErrorMessage( UserIdentity $user ): ?string {
		if ( !$user->isRegistered() ) {
			return 'growthexperiments-structuredtask-anonuser';
		}
		return null;
	}
}
