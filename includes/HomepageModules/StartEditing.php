<?php

namespace GrowthExperiments\HomepageModules;

use FormatJson;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\WelcomeSurvey;
use Html;
use IContextSource;
use OOUI\ButtonWidget;
use OOUI\Tag;

class StartEditing extends BaseTaskModule {

	/** @var bool In-process cache for isCompleted() */
	private $isCompleted;

	/** @var ExperimentUserManager */
	private $experimentUserManager;

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context, ExperimentUserManager $experimentUserManager ) {
		parent::__construct( 'start-startediting', $context, $experimentUserManager );
		$this->experimentUserManager = $experimentUserManager;
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		if ( $this->isCompleted === null ) {
			$this->isCompleted =
				$this->getContext()->getUser()->getBoolOption( SuggestedEdits::ACTIVATED_PREF );
		}
		return $this->isCompleted;
	}

	/**
	 * @inheritDoc
	 */
	public function isVisible() {
		return ( $this->getMode() !== HomepageModule::RENDER_DESKTOP ) || !$this->isCompleted();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		if ( $this->experimentUserManager->isUserInVariant(
			$this->getContext()->getUser(),
			'D'
		) ) {
			return 'suggestedEdits';
		}
		return 'edit';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		// Variant D
		if ( $this->experimentUserManager->isUserInVariant(
				$this->getContext()->getUser(),
				'D'
			) && $this->getMode() === HomepageModule::RENDER_MOBILE_SUMMARY ) {
			return $this->getContext()->msg(
				'growthexperiments-homepage-startediting-mobilesummary-header-variant-d'
			)->text();
		}

		// Variant A/C
		if ( $this->isCompleted() &&
			$this->getMode() === HomepageModule::RENDER_MOBILE_SUMMARY
		) {
			return $this->getContext()->msg( 'growthexperiments-homepage-startediting-button' )->text();
		} else {
			return $this->getContext()->msg( 'growthexperiments-homepage-startediting-header' )->text();
		}
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		if ( $this->experimentUserManager->isUserInVariant(
			$this->getContext()->getUser(),
			'D'
		) ) {
			return Html::rawElement( 'div', [],
				Html::element( 'p', [],
					$this->getContext()->msg(
						'growthexperiments-homepage-startediting-mobilesummary-body-variant-d'
					)->text() ) .
				new ButtonWidget( [
					'id' => 'mw-ge-homepage-startediting-mobilesummary-cta',
					'framed' => true,
					'flags' => [ 'progressive', 'primary' ],
					'label' => $this->getContext()->msg(
						'growthexperiments-homepage-startediting-mobilesummary-button-variant-d'
					)->text(),
					'infusable' => true,
					'button' => new Tag( 'span' ),
				] ) );
		} else {
			return parent::getMobileSummaryBody();
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		// Decide which message to use based on the user's WelcomeSurvey response. Messages:
		// growthexperiments-homepage-startediting-subheader-edit-typo
		// growthexperiments-homepage-startediting-subheader-add-image
		// growthexperiments-homepage-startediting-subheader-new-page
		$surveyResponse = FormatJson::decode(
			$this->getContext()->getUser()->getOption( WelcomeSurvey::SURVEY_PROP, '' )
		)->reason ?? 'none';
		$msgKey = "growthexperiments-homepage-startediting-subheader-$surveyResponse";

		// Fall back on -other if there is no specific message for this response
		if ( !$this->getContext()->msg( $msgKey )->exists() ) {
			$msgKey = 'growthexperiments-homepage-startediting-subheader-other';
		}
		return $this->getContext()->msg( $msgKey )->escaped();
	}

	/**
	 * @inheritDoc
	 */
	protected function getFooter() {
		return new ButtonWidget( [
			'id' => 'mw-ge-homepage-startediting-cta',
			'label' => $this->getContext()->msg( 'growthexperiments-homepage-startediting-button' )->text(),
			'flags' => [ 'progressive', 'primary' ],
			'active' => false,
			'infusable' => true,
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getModules() {
		return array_merge(
			parent::getModules(),
			[ 'ext.growthExperiments.Homepage.StartEditing' ]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		$variantD = $this->experimentUserManager->isUserInVariant(
			$this->getContext()->getUser(),
			'D'
		);
		return array_merge(
			parent::getModuleStyles(),
			[ 'oojs-ui.styles.icons-editing-core' ],
			// SuggestedEdits icon is in HelpPanel.icons
			$variantD ? [ 'ext.growthExperiments.HelpPanel.icons' ] : []
		);
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		return [
			'GEHomepageSuggestedEditsEnableTopics' =>
				SuggestedEdits::isTopicMatchingEnabled( $this->getContext() )
		];
	}

	/** @inheritDoc */
	protected function getModuleRoute() : string {
		return '';
	}
}
