<?php

namespace GrowthExperiments\MentorDashboard;

use ChangeTags;
use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataUpdater;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use ResourceLoaderContext;

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

	/**
	 * Tags mentee overview module uses to filter edits made by mentees
	 *
	 * @param ResourceLoaderContext $context
	 * @return array[]
	 */
	public static function getTagsToFilterBy( ResourceLoaderContext $context ) {
		return [
			'reverted' => [ ChangeTags::TAG_REVERTED ],
			'questions' => [
				Mentorship::MENTORSHIP_HELPPANEL_QUESTION_TAG,
				Mentorship::MENTORSHIP_MODULE_QUESTION_TAG
			]
		];
	}
}
