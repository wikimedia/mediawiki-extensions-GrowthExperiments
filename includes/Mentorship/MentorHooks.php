<?php

namespace GrowthExperiments\Mentorship;

use MediaWiki\Preferences\Hook\GetPreferencesHook;

class MentorHooks implements GetPreferencesHook {

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[ MentorPageMentorManager::MENTOR_PREF ] = [
			'type' => 'api',
		];
	}

}
