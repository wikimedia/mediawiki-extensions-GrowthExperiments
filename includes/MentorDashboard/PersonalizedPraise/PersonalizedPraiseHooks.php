<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use MediaWiki\Preferences\Hook\GetPreferencesHook;

class PersonalizedPraiseHooks implements GetPreferencesHook {

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[ PraiseworthyConditionsLookup::WAS_PRAISED_PREF ] = [
			'type' => 'api',
		];
		$preferences[ PraiseworthyConditionsLookup::SKIPPED_UNTIL_PREF ] = [
			'type' => 'api',
		];
		$preferences[ PersonalizedPraiseSettings::PREF_NAME ] = [
			'type' => 'api',
		];
	}
}
