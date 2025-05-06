<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\ExperimentUserManager;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use OOUI\IconWidget;

class StartEmail extends BaseModule {

	/** @inheritDoc */
	protected static $supportedModes = [
		self::RENDER_DESKTOP,
		self::RENDER_MOBILE_SUMMARY
		// RENDER_MOBILE_DETAILS is not supported
	];

	/** @var string */
	protected $emailState;

	/** @inheritDoc */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager
	) {
		parent::__construct( 'startemail', $context, $wikiConfig, $experimentUserManager );

		$user = $this->getContext()->getUser();
		if ( $user->getEmail() ) {
			$this->emailState = self::MODULE_STATE_UNCONFIRMED;
		} else {
			$this->emailState = self::MODULE_STATE_NOEMAIL;
		}
	}

	/** @inheritDoc */
	protected function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(),
			[ 'oojs-ui.styles.icons-alerts' ]
		);
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		// Not used, but must be implemented because it's abstract in the parent class
		return '';
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		// Not used, but must be implemented because it's abstract in the parent class
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
		return $this->getEmailIcon() .
			Html::rawElement(
				'span',
				[ 'class' => 'growthexperiments-homepage-startemail-address-wrapper' ],
				$this->getEmailAddress() .
					$this->getContext()->msg( 'word-separator' )->escaped() .
					$this->getEmailAction()
			);
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return $this->getEmailIcon() .
			Html::rawElement(
				'span',
				[ 'class' => 'growthexperiments-homepage-startemail-address-wrapper' ],
				$this->getEmailAddressRaw() .
					$this->getContext()->msg( 'word-separator' )->escaped() .
					$this->getEmailAction()
			);
	}

	/**
	 * Get the icon to put before the email address ('message' icon, either black or blue)
	 * @return IconWidget
	 */
	protected function getEmailIcon() {
		return new IconWidget( [
			'icon' => 'message',
			'flags' => $this->emailState === self::MODULE_STATE_NOEMAIL ? [ 'progressive' ] : []
		] );
	}

	/**
	 * Get the email address, if there is one, wrapped in an i18n message
	 * @return string HTML
	 */
	protected function getEmailAddress() {
		if ( $this->emailState === self::MODULE_STATE_NOEMAIL ) {
			return '';
		}
		return $this->getContext()->msg( 'growthexperiments-homepage-email-header-startemail' )
			->rawParams( $this->getEmailAddressRaw() )
			->escaped();
	}

	/**
	 * Get the email address, if there is one, without the wrapper message
	 * @return string HTML
	 */
	protected function getEmailAddressRaw() {
		if ( $this->emailState === self::MODULE_STATE_NOEMAIL ) {
			return '';
		}
		return Html::element(
			'span',
			[ 'class' => 'growthexperiments-homepage-startemail-address' ],
			$this->getContext()->getUser()->getEmail()
		);
	}

	/**
	 * Get the link that comes after the email address. For the NOEMAIL state, this is the only
	 * thing shown besides the icon.
	 * @return string HTML
	 */
	protected function getEmailAction() {
		$linkAttrs = [
			'data-link-id' => 'email-' . $this->emailState
		];
		$wrapInParentheses = false;
		$label = '';
		if ( $this->emailState === self::MODULE_STATE_NOEMAIL ) {
			$label = $this->getContext()->msg( 'growthexperiments-homepage-email-button-noemail' )->text();
			$linkAttrs['href'] = SpecialPage::getTitleFor( 'ChangeEmail' )->getLinkURL( [
				'returnto' => $this->getContext()->getTitle()->getPrefixedText()
			] );
			$linkAttrs['class'] = 'growthexperiments-homepage-startemail-noemail-link';
		} elseif ( $this->emailState === self::MODULE_STATE_UNCONFIRMED ) {
			$label = $this->getContext()->msg( 'growthexperiments-homepage-email-confirmlink' )->text();
			$wrapInParentheses = true;
			$linkAttrs['href'] = SpecialPage::getTitleFor( 'Confirmemail' )->getLinkURL();
		}
		$link = Html::element( 'a', $linkAttrs, $label );
		if ( $wrapInParentheses ) {
			$link = $this->getContext()->msg( 'parentheses' )->rawParams( $link )->escaped();
		}
		return $link;
	}

	/** @inheritDoc */
	public function getState() {
		return $this->emailState;
	}
}
