<?php

namespace GrowthExperiments\MentorDashboard;

use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataUpdater;
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
		$preferences[ MentorDashboardDiscoveryHooks::MENTOR_DASHBOARD_SEEN_PREF ] = [
			'type' => 'api',
		];
		$preferences[ MenteeOverviewDataUpdater::LAST_UPDATE_PREFERENCE ] = [
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
			MentorDashboardDiscoveryHooks::MENTOR_DASHBOARD_SEEN_PREF => 0,
			MenteeOverviewDataUpdater::LAST_UPDATE_PREFERENCE => null,
		];
	}
}
