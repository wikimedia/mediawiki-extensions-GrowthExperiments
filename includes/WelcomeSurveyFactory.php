<?php

namespace GrowthExperiments;

use MediaWiki\Context\IContextSource;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\Registration\UserRegistrationLookup;

/**
 * Factory class for WelcomeSurvey
 *
 * This exists to be able to easily modify services
 * passed to WelcomeSurvey.
 */
class WelcomeSurveyFactory {
	public function __construct(
		private LanguageNameUtils $languageNameUtils,
		private UserOptionsManager $userOptionsManager,
		private UserRegistrationLookup $userRegistrationLookup,
		private bool $ulsInstalled,
	) {
	}

	public function newWelcomeSurvey( IContextSource $context ): WelcomeSurvey {
		return new WelcomeSurvey(
			$context,
			$this->languageNameUtils,
			$this->userOptionsManager,
			$this->userRegistrationLookup,
			$this->ulsInstalled,
		);
	}
}
