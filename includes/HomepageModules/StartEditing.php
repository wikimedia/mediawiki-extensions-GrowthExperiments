<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\ExperimentUserManager;
use IContextSource;
use MediaWiki\User\UserOptionsLookup;

class StartEditing extends BaseModule {

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

		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return '';
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

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return '';
	}

	/** @inheritDoc */
	public function getState(): string {
		return SuggestedEdits::isActivated( $this->getContext()->getUser(), $this->userOptionsLookup ) ?
			self::MODULE_STATE_COMPLETE :
			self::MODULE_STATE_INCOMPLETE;
	}
}
