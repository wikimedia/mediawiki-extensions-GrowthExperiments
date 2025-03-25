<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\WelcomeSurveyFactory;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPageFactory;
use OOUI\ButtonInputWidget;
use OOUI\IconWidget;

/**
 * A module for displaying a dismissable reminder to users who have not filled out the welcome survey.
 */
class WelcomeSurveyReminder extends BaseModule {

	/** @inheritDoc */
	protected static $supportedModes = [
		self::RENDER_DESKTOP,
		self::RENDER_MOBILE_SUMMARY,
		// RENDER_MOBILE_DETAILS is not supported
	];

	/** @var SpecialPageFactory */
	private $specialPageFactory;

	/** @var WelcomeSurveyFactory */
	private $welcomeSurveyFactory;

	/**
	 * @inheritDoc
	 * @param SpecialPageFactory $specialPageFactory
	 * @param WelcomeSurveyFactory $welcomeSurveyFactory
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager,
		SpecialPageFactory $specialPageFactory,
		WelcomeSurveyFactory $welcomeSurveyFactory
	) {
		parent::__construct( 'welcomesurveyreminder', $context, $wikiConfig, $experimentUserManager );
		$this->specialPageFactory = $specialPageFactory;
		$this->welcomeSurveyFactory = $welcomeSurveyFactory;
	}

	/**
	 * The module is enabled if the user has not filled out the welcome survey, or they have
	 * registered more than 60 days ago.
	 * @inheritDoc
	 */
	protected function canRender() {
		return $this->welcomeSurveyFactory->newWelcomeSurvey( $this->getContext() )->isUnfinished();
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return '';
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return '';
	}

	/** @inheritDoc */
	protected function getHeader() {
		return '';
	}

	/** @inheritDoc */
	protected function getMobileSummaryHeader() {
		return '';
	}

	private function getBodyContent(): string {
		$welcomeSurvey = $this->welcomeSurveyFactory->newWelcomeSurvey( $this->getContext() );
		$group = $welcomeSurvey->getGroup( true );
		$surveyTitle = $this->specialPageFactory->getTitleForAlias( 'WelcomeSurvey' );
		$returnTo = $this->specialPageFactory->getTitleForAlias( 'Homepage' )->getPrefixedText();
		$returnToQuery = wfArrayToCgi( [ 'source' => 'welcomesurvey-originalcontext' ] );
		$surveyUrl = $surveyTitle->getLocalURL(
			$welcomeSurvey->getRedirectUrlQuery( $group, $returnTo, $returnToQuery )
		);
		$surveyLink = Html::element(
			'a',
			[
				'href' => $surveyUrl,
				'class' => 'welcomesurvey-reminder-link',
				'data-link-id' => 'welcomesurvey-reminder',
			],
			$this->getContext()->msg( 'welcomesurvey-reminder-link' )->text()
		);
		$reminderHtml = $this->getContext()->msg( 'welcomesurvey-reminder' )
			->rawParams( $surveyLink )
			->parse();
		// wrap for flexbox friendliness
		$reminderBlock = Html::rawElement( 'p', [
			'class' => 'welcomesurvey-reminder-message-block',
		], $reminderHtml );

		$messageIcon = new IconWidget( [
			'icon' => 'feedback',
			'classes' => [ 'welcomesurvey-reminder-feedback-icon' ],
		] );

		$ajaxSkipUrl = wfScript( 'rest' ) . '/growthexperiments/v0/welcomesurvey/skip';
		$noJsSkipUrl = $this->specialPageFactory->getTitleForAlias( 'WelcomeSurvey' )
			->getSubpage( 'skip' )
			->getLocalURL();
		$disableButton = new ButtonInputWidget( [
			'type' => 'submit',
			'icon' => 'close',
			'framed' => false,
			'classes' => [ 'welcomesurvey-reminder-dismiss' ],
			'label' => $this->getContext()->msg( 'welcomesurvey-reminder-dismiss' )->text(),
			'invisibleLabel' => true,
		] );
		$disableButton->setAttributes( [ 'data-ajax' => $ajaxSkipUrl ] );
		$disableButton->setAttributes( [ 'data-link-id' => 'welcomesurvey-skip' ] );
		$disableToken = Html::element( 'input', [
			'type' => 'hidden',
			'name' => 'token',
			'value' => $this->getContext()->getCsrfTokenSet()->getToken( 'welcomesurvey' ),
		] );
		$disableForm = HTML::rawElement( 'form', [
			'action' => $noJsSkipUrl,
			'method' => 'POST',
		], $disableButton . $disableToken );

		return $messageIcon . $reminderBlock . $disableForm;
	}

	/** @inheritDoc */
	protected function getBody() {
		return $this->getBodyContent();
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return $this->getBodyContent();
	}

	/** @inheritDoc */
	public function getState() {
		return $this->canRender() ? self::MODULE_STATE_ACTIVATED : self::MODULE_STATE_UNACTIVATED;
	}

}
