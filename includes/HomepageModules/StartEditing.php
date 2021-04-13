<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModule;
use IContextSource;

class StartEditing extends BaseTaskModule {

	/** @var bool In-process cache for isCompleted() */
	private $isCompleted;

	/** @var ExperimentUserManager */
	private $experimentUserManager;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager
	) {
		parent::__construct( 'start-startediting', $context, $wikiConfig, $experimentUserManager );
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
		return $this->getMode() === HomepageModule::RENDER_DESKTOP ? null : 'suggestedEdits';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->getContext()->msg(
			'growthexperiments-homepage-suggested-edits-header'
		)->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return '';
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
			[ 'oojs-ui.styles.icons-editing-core' ],
			// SuggestedEdits icon is in HelpPanel.icons
			[ 'ext.growthExperiments.HelpPanel.icons' ]
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
