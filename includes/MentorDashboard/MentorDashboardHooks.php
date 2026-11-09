<?php

namespace GrowthExperiments\MentorDashboard;

use GrowthExperiments\HomepageModules\Mentorship;
use GrowthExperiments\MentorDashboard\MenteeOverview\MenteeOverviewDataUpdater;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use GrowthExperiments\MentorDashboard\Modules\MenteeOverview;
use MediaWiki\ChangeTags\ChangeTags;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderExcludeUserOptionsHook;

class MentorDashboardHooks implements
	GetPreferencesHook,
	ResourceLoaderExcludeUserOptionsHook
{
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
		$preferences[ MenteeOverview::PRESETS_PREF ] = [
			'type' => 'api',
		];
	}

	/** @inheritDoc */
	public function onResourceLoaderExcludeUserOptions(
		array &$keysToExclude,
		Context $context
	): void {
		$keysToExclude = array_merge( $keysToExclude, [
			StarredMenteesStore::STARRED_MENTEES_PREFERENCE,
			MentorDashboardDiscoveryHooks::MENTOR_DASHBOARD_SEEN_PREF,
			MenteeOverviewDataUpdater::LAST_UPDATE_PREFERENCE,
		] );
	}

	/**
	 * Tags mentee overview module uses to filter edits made by mentees
	 *
	 * @param Context $context
	 * @return array[]
	 */
	public static function getTagsToFilterBy( Context $context ) {
		return [
			'reverted' => [ ChangeTags::TAG_REVERTED ],
			'questions' => [
				Mentorship::MENTORSHIP_HELPPANEL_QUESTION_TAG,
				Mentorship::MENTORSHIP_MODULE_QUESTION_TAG,
			],
		];
	}
}
