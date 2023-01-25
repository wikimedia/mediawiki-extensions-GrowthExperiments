<?php

namespace GrowthExperiments;

use IContextSource;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\User\UserOptionsManager;

/**
 * Factory class for WelcomeSurvey
 *
 * This exists to be able to easily modify services
 * passed to WelcomeSurvey.
 */
class WelcomeSurveyFactory {
	private LanguageNameUtils $languageNameUtils;
	private UserOptionsManager $userOptionsManager;

	/**
	 * @param LanguageNameUtils $languageNameUtils
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct(
		LanguageNameUtils $languageNameUtils,
		UserOptionsManager $userOptionsManager
	) {
		$this->languageNameUtils = $languageNameUtils;
		$this->userOptionsManager = $userOptionsManager;
	}

	/**
	 * @param IContextSource $context
	 * @return WelcomeSurvey
	 */
	public function newWelcomeSurvey( IContextSource $context ): WelcomeSurvey {
		return new WelcomeSurvey(
			$context,
			$this->languageNameUtils,
			$this->userOptionsManager
		);
	}
}
