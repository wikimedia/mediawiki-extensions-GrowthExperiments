<?php

namespace GrowthExperiments\HomepageModules;

use IContextSource;

class SuggestedEdits extends BaseModule {

	const ACTIVATED_PREF = 'growthexperiments-homepage-suggestededits-activated';

	/** @inheritDoc */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'suggested-edits', $context );
	}

	/**
	 * Check whether suggested edits have been activated.
	 * Before activation, suggested edits are exposed via the StartEditing module;
	 * after activation (which happens by interacting with that module) via this one.
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function isActivated( IContextSource $context ) {
		return (bool)$context->getUser()->getBoolOption( self::ACTIVATED_PREF );
	}

	/** @inheritDoc */
	public function getState() {
		return self::isActivated( $this->getContext() ) ?
			self::MODULE_STATE_ACTIVATED :
			self::MODULE_STATE_UNACTIVATED;
	}

	/** @inheritDoc */
	protected function canRender() {
		return self::isActivated( $this->getContext() );
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-suggested-edits-header' )
			->text();
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'lightbulb';
	}

	/** @inheritDoc */
	protected function getBody() {
		return '';
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return '';
	}
}
