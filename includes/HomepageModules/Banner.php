<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\ExperimentUserManager;
use Html;
use IContextSource;

/**
 * A module for displaying some text that can be specified on-wiki. This can be used as a low-key
 * sitenotice aimed at new users.
 */
class Banner extends BaseModule {

	public const MESSAGE_KEY = 'growth-homepage-banner';

	/** @inheritDoc */
	protected static $supportedModes = [
		self::RENDER_DESKTOP,
		self::RENDER_MOBILE_SUMMARY
		// RENDER_MOBILE_DETAILS is not supported
	];

	/**
	 * Check whether the module is enabled (ie. if there's a banner message set by the wiki admin).
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function isEnabled( IContextSource $context ) {
		return !$context->msg( self::MESSAGE_KEY )->isDisabled();
	}

	/** @inheritDoc */
	public function __construct( IContextSource $context, ExperimentUserManager $experimentUserManager ) {
		parent::__construct( 'banner', $context, $experimentUserManager );
	}

	/** @inheritDoc */
	protected function canRender() {
		return self::isEnabled( $this->getContext() );
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

	/** @inheritDoc */
	protected function getBody() {
		return Html::rawElement(
			'div',
			[ 'data-link-group-id' => 'banner' ],
			$this->getContext()->msg( self::MESSAGE_KEY )->parse()
		);
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return $this->getContext()->msg( self::MESSAGE_KEY )->parse();
	}

	/** @inheritDoc */
	public function getState() {
		return $this->canRender() ? 'enabled' : 'disabled';
	}

}
