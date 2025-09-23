<?php

namespace GrowthExperiments\Mentorship\Hooks;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\MentorManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderExcludeUserOptionsHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;

/**
 * Mentorship-related hooks that touch user-preferences
 *
 * Many mentorship hooks depend on MentorManager, which depends on session. User option related
 * hooks must run before MentorManager has a chance, so we keep them separately.
 */
class MentorUserOptionsHooks implements
	GetPreferencesHook,
	UserGetDefaultOptionsHook,
	ResourceLoaderExcludeUserOptionsHook
{

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[ MentorManager::MENTORSHIP_ENABLED_PREF ] = [
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
			MentorManager::MENTORSHIP_ENABLED_PREF => MentorManager::MENTORSHIP_ENABLED,
		];
	}

	/** @inheritDoc */
	public function onResourceLoaderExcludeUserOptions(
		array &$keysToExclude,
		Context $context
	): void {
		$keysToExclude = array_merge( $keysToExclude, [
			MentorStatusManager::MENTOR_AWAY_TIMESTAMP_PREF,
		] );
	}

}
