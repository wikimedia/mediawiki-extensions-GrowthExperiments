<?php

declare( strict_types = 1 );

namespace GrowthExperiments\AccountSetup;

use MediaWiki\Preferences\Hook\GetPreferencesHook;

class AccountSetupHooks implements GetPreferencesHook {
	public const string INTEREST_ARTICLES_PROP = 'growthexperiments-interest-articles-editing';

	/**
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ): void {
		$preferences[self::INTEREST_ARTICLES_PROP] = [
			'type' => 'api',
		];
	}
}
