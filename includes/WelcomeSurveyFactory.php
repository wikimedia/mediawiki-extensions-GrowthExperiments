<?php

namespace GrowthExperiments;

use MediaWiki\Context\IContextSource;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\User\Options\UserOptionsManager;

/**
 * Factory class for WelcomeSurvey
 *
 * This exists to be able to easily modify services
 * passed to WelcomeSurvey.
 */
class WelcomeSurveyFactory {
	private LanguageNameUtils $languageNameUtils;
	private UserOptionsManager $userOptionsManager;
	private bool $ulsInstalled;

	public function __construct(
		LanguageNameUtils $languageNameUtils,
		UserOptionsManager $userOptionsManager,
		bool $ulsInstalled
	) {
		$this->languageNameUtils = $languageNameUtils;
		$this->userOptionsManager = $userOptionsManager;
		$this->ulsInstalled = $ulsInstalled;
	}

	public function newWelcomeSurvey( IContextSource $context ): WelcomeSurvey {
		return new WelcomeSurvey(
			$context,
			$this->languageNameUtils,
			$this->userOptionsManager,
			$this->ulsInstalled
		);
	}
}
