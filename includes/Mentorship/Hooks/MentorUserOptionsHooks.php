<?php

namespace GrowthExperiments\Mentorship\Hooks;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\MentorPageMentorManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;

/**
 * Mentorship-related hooks that touch user-preferences
 *
 * Many mentorship hooks depend on MentorManager, which depends on session. User option related
 * hooks must run before MentorManager has a chance, so we keep them separately.
 */
class MentorUserOptionsHooks implements
	GetPreferencesHook,
	UserGetDefaultOptionsHook
{

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[ MentorPageMentorManager::MENTORSHIP_ENABLED_PREF ] = [
			'type' => 'api'
		];
		$preferences[ MentorWeightManager::MENTORSHIP_WEIGHT_PREF ] = [
			'type' => 'api',
		];
		$preferences[ MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF ] = [
			'type' => 'api',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions += [
			MentorPageMentorManager::MENTORSHIP_ENABLED_PREF => 1,
			MentorWeightManager::MENTORSHIP_WEIGHT_PREF => MentorWeightManager::MENTORSHIP_DEFAULT_WEIGHT,
		];
	}
}
