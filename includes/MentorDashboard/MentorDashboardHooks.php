<?php

namespace GrowthExperiments\MentorDashboard;

use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;

class MentorDashboardHooks implements GetPreferencesHook, UserGetDefaultOptionsHook {
	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[ StarredMenteesStore::STARRED_MENTEES_PREFERENCE ] = [
			'type' => 'api',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		// This is here to make use of the constant
		$defaultOptions += [
			StarredMenteesStore::STARRED_MENTEES_PREFERENCE => '',
		];
	}
}
