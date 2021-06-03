<?php

namespace GrowthExperiments;

use IContextSource;
use MediaWiki\Languages\LanguageNameUtils;

/**
 * Factory class for WelcomeSurvey
 *
 * This exists to be able to easily modify services
 * passed to WelcomeSurvey.
 */
class WelcomeSurveyFactory {
	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/**
	 * @param LanguageNameUtils $languageNameUtils
	 */
	public function __construct(
		LanguageNameUtils $languageNameUtils
	) {
		$this->languageNameUtils = $languageNameUtils;
	}

	/**
	 * @param IContextSource $context
	 * @return WelcomeSurvey
	 */
	public function newWelcomeSurvey( IContextSource $context ): WelcomeSurvey {
		return new WelcomeSurvey( $context, $this->languageNameUtils );
	}
}
