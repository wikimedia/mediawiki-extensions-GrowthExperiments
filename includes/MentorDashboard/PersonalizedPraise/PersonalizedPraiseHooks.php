<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;

class PersonalizedPraiseHooks implements
	GetPreferencesHook,
	UserGetDefaultOptionsHook
{
	public function __construct() {
	}

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

	/**
	 * @inheritDoc
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions += [
			PraiseworthyConditionsLookup::WAS_PRAISED_PREF => false,
			PraiseworthyConditionsLookup::SKIPPED_UNTIL_PREF => null,
			PersonalizedPraiseSettings::PREF_NAME => '{}',
		];
	}
}
