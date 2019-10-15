<?php

namespace GrowthExperiments\HomepageModules;

use IContextSource;
use Html;
use ExtensionRegistry;

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
		$extensionRegistry = ExtensionRegistry::getInstance();
		return self::isActivated( $this->getContext() ) &&
			   $extensionRegistry->isLoaded( 'TextExtracts' ) &&
			   $extensionRegistry->isLoaded( 'PageViewInfo' ) &&
			   $extensionRegistry->isLoaded( 'PageImages' );
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
		return Html::rawElement(
			'div', [ 'class' => 'suggested-edits-module-wrapper' ],
			Html::element( 'div', [ 'class' => 'suggested-edits-pager' ] ) .
			Html::rawElement( 'div', [ 'class' => 'suggested-edits-card-wrapper' ],
				Html::element( 'div', [ 'class' => 'suggested-edits-previous' ] ) .
				Html::element( 'div', [ 'class' => 'suggested-edits-card' ] ) .
				Html::element( 'div', [ 'class' => 'suggested-edits-next' ] ) )
		);
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return '';
	}

	/** @inheritDoc */
	protected function getModules() {
		return array_merge(
			parent::getModules(),
			[ 'ext.growthExperiments.Homepage.SuggestedEdits' ]
		);
	}
}
