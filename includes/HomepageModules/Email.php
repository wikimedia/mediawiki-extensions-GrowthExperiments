<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\ExperimentUserManager;
use Html;
use IContextSource;
use OOUI\ButtonWidget;
use SpecialPage;

class Email extends BaseTaskModule {

	/** @var string */
	protected $emailState = self::MODULE_STATE_NOEMAIL;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager
	) {
		parent::__construct( 'start-email', $context, $wikiConfig, $experimentUserManager );

		$user = $this->getContext()->getUser();
		if ( $user->isEmailConfirmed() ) {
			$this->emailState = self::MODULE_STATE_CONFIRMED;
		} elseif ( $user->getEmail() ) {
			$this->emailState = self::MODULE_STATE_UNCONFIRMED;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		return $this->emailState === self::MODULE_STATE_CONFIRMED;
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'message';
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return [ 'oojs-ui.styles.icons-alerts' ];
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		// growthexperiments-homepage-email-header-noemail,
		// growthexperiments-homepage-email-header-unconfirmed,
		// growthexperiments-homepage-email-header-confirmed
		$msgKey = "growthexperiments-homepage-email-header-{$this->emailState}";
		return $this->getContext()->msg( $msgKey )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		// growthexperiments-homepage-email-text-noemail,
		// growthexperiments-homepage-email-text-unconfirmed,
		// growthexperiments-homepage-email-text-confirmed
		$msgKey = "growthexperiments-homepage-email-text-{$this->emailState}";
		$messageText = $this->getContext()->msg( $msgKey )
			->params( $this->getContext()->getUser()->getName() )
			->escaped();
		return $messageText . $this->getEmailAndChangeLink();
	}

	/**
	 * @inheritDoc
	 */
	protected function getFooter() {
		// growthexperiments-homepage-email-button-noemail,
		// growthexperiments-homepage-email-button-unconfirmed,
		// growthexperiments-homepage-email-button-confirmed
		$buttonMsg = "growthexperiments-homepage-email-button-{$this->emailState}";
		$buttonConfig = [ 'label' => $this->getContext()->msg( $buttonMsg )->text() ];
		if ( $this->emailState === self::MODULE_STATE_CONFIRMED ) {
			$buttonConfig += [
				'href' => $this->getEmailPrefsURL(),
			];
		} elseif ( $this->emailState === self::MODULE_STATE_UNCONFIRMED ) {
			$buttonConfig += [
				'href' => SpecialPage::getTitleFor( 'Confirmemail' )->getLinkURL(),
				'flags' => [ 'progressive' ]
			];
		} else {
			$buttonConfig += [
				'href' => SpecialPage::getTitleFor( 'ChangeEmail' )->getLinkURL( [
					'returnto' => $this->getContext()->getTitle()->getPrefixedText()
				] ),
				'flags' => [ 'progressive' ],
			];
		}

		$button = new ButtonWidget( $buttonConfig );
		$button->setAttributes( [ 'data-link-id' => 'email-' . $this->emailState ] );
		return $button;
	}

	/**
	 * Build the email address and "(change)" link.
	 *
	 * This only appears in the unconfirmed state. If we're in a different state, this method returns
	 * an empty string.
	 * @return string HTML
	 */
	protected function getEmailAndChangeLink() {
		if ( $this->emailState !== self::MODULE_STATE_UNCONFIRMED ) {
			return '';
		}
		$email = $this->getContext()->getUser()->getEmail();
		return Html::rawElement(
			'div',
			[ 'class' => 'growthexperiments-homepage-email-change' ],
			Html::element(
				'span',
				[
					'class' => 'growthexperiments-homepage-email-change-address',
					'title' => $email
				],
				$email
			) . $this->getContext()->msg( 'word-separator' )->text()
			. $this->getContext()->msg( 'parentheses' )->rawParams(
				Html::element(
					'a',
					[
						'class' => 'growthexperiments-homepage-email-change-link',
						'href' => $this->getEmailPrefsURL(),
					],
					$this->getContext()->msg( 'growthexperiments-homepage-email-changelink' )->text()
				)
			)->escaped()
		);
	}

	/**
	 * Get the URL for the "email" section on Special:Preferences
	 * @return string URL
	 */
	protected function getEmailPrefsURL() {
		return SpecialPage::getTitleFor( 'Preferences', false, 'mw-prefsection-personal-email' )
			->getLinkURL();
	}

	/**
	 * @inheritDoc
	 */
	public function getState() {
		return $this->emailState;
	}

	/**
	 * @inheritDoc
	 */
	protected function getCssClasses() {
		return array_merge(
			parent::getCssClasses(),
			[ 'growthexperiments-homepage-email-' . $this->getState() ]
		);
	}
}
