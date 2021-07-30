<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\ExperimentUserManager;
use IContextSource;
use MediaWiki\User\UserOptionsLookup;

class StartEditing extends BaseTaskModule {

	/** @var bool In-process cache for isCompleted() */
	private $isCompleted;

	/** @var ExperimentUserManager */
	private $experimentUserManager;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager,
		UserOptionsLookup $userOptionsLookup
	) {
		parent::__construct( 'start-startediting', $context, $wikiConfig, $experimentUserManager );

		$this->experimentUserManager = $experimentUserManager;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		if ( $this->isCompleted === null ) {
			$this->isCompleted = $this->userOptionsLookup->getBoolOption(
				$this->getContext()->getUser(),
				SuggestedEdits::ACTIVATED_PREF
			);
		}
		return $this->isCompleted;
	}

	/**
	 * @inheritDoc
	 */
	public function isVisible() {
		return ( $this->getMode() !== self::RENDER_DESKTOP ) || !$this->isCompleted();
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return $this->getMode() === self::RENDER_DESKTOP ? null : 'suggestedEdits';
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
			[ 'ext.growthExperiments.icons' ]
		);
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		return [
			'GEHomepageSuggestedEditsEnableTopics' =>
				SuggestedEdits::isTopicMatchingEnabled(
					$this->getContext(),
					$this->userOptionsLookup
				)
		];
	}

	/** @inheritDoc */
	protected function getModuleRoute(): string {
		return '';
	}
}
