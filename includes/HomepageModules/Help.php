<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\AbstractExperimentManager;
use GrowthExperiments\HelpPanel;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

class Help extends BaseModule {
	public const HELP_MODULE_QUESTION_TAG = 'help module question';

	/**
	 * @inheritDoc
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		AbstractExperimentManager $experimentUserManager
	) {
		parent::__construct( 'help', $context, $wikiConfig, $experimentUserManager );
	}

	/** @inheritDoc */
	protected function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(),
			[ 'oojs-ui.styles.icons-interactions' ]
		);
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
			$this->getGrowthWikiConfig()
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
