<?php

namespace GrowthExperiments\Mentorship\Hooks;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\MentorManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderExcludeUserOptionsHook;

/**
 * Mentorship-related hooks that touch user-preferences
 *
 * Many mentorship hooks depend on MentorManager, which depends on session. User option related
 * hooks must run before MentorManager has a chance, so we keep them separately.
 */
class MentorUserOptionsHooks implements
	GetPreferencesHook,
	ResourceLoaderExcludeUserOptionsHook
{

	/**
	 * Whether the mentee dismissed the intro message of the Personal Dashboard
	 * mentorship module. Not on that module class, which needs PersonalDashboard.
	 */
	public const string PREINTRO_DISMISSED_PREF = 'growthexperiments-personaldashboard-mentorship-preintro-dismissed';

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[ MentorManager::MENTORSHIP_ENABLED_PREF ] = [
			'type' => 'api',
		];
		$preferences[ MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF ] = [
			'type' => 'api',
		];
		$preferences[ self::PREINTRO_DISMISSED_PREF ] = [
			'type' => 'api',
		];
	}

	/** @inheritDoc */
	public function onResourceLoaderExcludeUserOptions(
		array &$keysToExclude,
		Context $context
	): void {
		$keysToExclude = array_merge( $keysToExclude, [
			MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF,
			self::PREINTRO_DISMISSED_PREF,
		] );
	}

}
