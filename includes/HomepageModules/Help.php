<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HelpPanel;
use Html;
use IContextSource;

class Help extends BaseModule {

	const HELP_MODULE_QUESTION_TAG = 'help module question';
	/**
	 * @var string
	 */
	private $helpDeskUrl;

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context, ExperimentUserManager $experimentUserManager ) {
		parent::__construct( 'help', $context, $experimentUserManager );
		$this->helpDeskUrl = HelpPanel::getHelpDeskTitle( $context->getConfig() )->getLinkURL();
	}

	/** @inheritDoc */
	public function getHeader() {
		return $this->getHeaderIcon(
			$this->getHeaderIconName(),
			$this->shouldInvertHeaderIcon()
		) . $this->getHeaderTextElement();
	}

	/** @inheritDoc */
	protected function getMobileSummaryHeader() {
		return $this->getHeaderTextElement();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->getContext()->msg( 'growthexperiments-homepage-help-header' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheader() {
		return $this->getContext()->msg( 'growthexperiments-homepage-help-subheader' )->escaped();
	}

	/**
	 * @inheritDoc
	 */
	protected function getJsConfigVars() {
		return HelpPanel::getUserEmailConfigVars( $this->getContext()->getUser() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		$helpPanelLinkData = HelpPanel::getHelpPanelLinks(
			$this->getContext(),
			$this->getContext()->getConfig()
		);
		return $helpPanelLinkData['helpPanelLinks'] . $helpPanelLinkData['viewMoreLink'];
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return Html::element(
			'div',
			[ 'class' => 'growthexperiments-homepage-module-text-light' ],
			$this->getContext()->msg( 'growthexperiments-homepage-help-mobilebody' )->text()
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'help';
	}
}
