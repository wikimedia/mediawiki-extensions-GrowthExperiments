<?php

namespace GrowthExperiments\HomepageModules;

use FormatJson;
use GrowthExperiments\WelcomeSurvey;
use IContextSource;
use OOUI\ButtonWidget;

class StartEditing extends BaseTaskModule {

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'start-startediting', $context );
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		// TODO: hide module when in completed state
		return $this->getContext()->getUser()->getBoolOption( SuggestedEdits::ACTIVATED_PREF );
	}

	/**
	 * @inheritDoc
	 */
	public function isVisible() {
		return !$this->isCompleted();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'edit';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->getContext()->msg( 'growthexperiments-homepage-startediting-header' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		// Decide which message to use based on the user's WelcomeSurvey response. Messages:
		// growthexperiments-homepage-startediting-subheader-edit-info-add-change
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
		return array_merge(
			parent::getModuleStyles(),
			[ 'oojs-ui.styles.icons-editing-core' ]
		);
	}
}
