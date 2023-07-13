<?php

namespace GrowthExperiments\NewcomerTasks;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;

abstract class AbstractSubmissionHandler {

	/**
	 * Return the message key for the error message if the user is unregistered,
	 * otherwise return null.
	 *
	 * @param UserIdentityUtils $utils
	 * @param UserIdentity $user
	 * @return string|null
	 */
	protected static function getUserErrorMessage(
		UserIdentityUtils $utils,
		UserIdentity $user
	): ?string {
		if ( !$utils->isNamed( $user ) ) {
			return 'growthexperiments-structuredtask-anonuser';
		}
		return null;
	}
}
