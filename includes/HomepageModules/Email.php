<?php
namespace GrowthExperiments\HomepageModules;

use IContextSource;
use Html;
use OOUI\ButtonWidget;
use OOUI\IconWidget;
use SpecialPage;

class Email extends BaseTaskModule {

	protected $emailState = null;

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'email', $context );

		$user = $this->getContext()->getUser();
		if ( $user->isEmailConfirmed() ) {
			$this->emailState = 'confirmed';
		} elseif ( $user->getEmail() ) {
			// TODO do we want to distinguish $user->isEmailConfirmationPending()?
			$this->emailState = 'unconfirmed';
		} else {
			$this->emailState = 'noemail';
		}
	}

	/**
	 * @inheritDoc
	 */
	public function isCompleted() {
		return $this->emailState === 'confirmed';
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeader() {
		// growthexperiments-homepage-email-header-noemail,
		// growthexperiments-homepage-email-header-unconfirmed,
		// growthexperiments-homepage-email-header-confirmed
		$msgKey = "growthexperiments-homepage-email-header-{$this->emailState}";
		$icon = $this->emailState === 'confirmed' ? 'check' : 'message';
		return new IconWidget( [ 'icon' => $icon ] ) .
			Html::element( 'span', [],
				$this->getContext()->msg( $msgKey )
					->params( $this->getContext()->getUser()->getName() )
					->text()
			);
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheader() {
		// growthexperiments-homepage-email-text-noemail,
		// growthexperiments-homepage-email-text-unconfirmed,
		// growthexperiments-homepage-email-text-confirmed
		return $this->getContext()->msg( "growthexperiments-homepage-email-text-{$this->emailState}" )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		// growthexperiments-homepage-email-button-noemail,
		// growthexperiments-homepage-email-button-unconfirmed,
		// growthexperiments-homepage-email-button-confirmed
		$buttonMsg = "growthexperiments-homepage-email-button-{$this->emailState}";
		$buttonConfig = [ 'label' => $this->getContext()->msg( $buttonMsg )->text() ];
		if ( $this->emailState === 'confirmed' ) {
			$buttonConfig += [
				'href' => SpecialPage::getTitleFor( 'Preferences', false, 'mw-prefsection-personal-email' )
					->getLinkURL()
			];
		} elseif ( $this->emailState === 'unconfirmed' ) {
			$buttonConfig += [
				'href' => SpecialPage::getTitleFor( 'Confirmemail' )->getLinkURL(),
				'flags' => [ 'primary', 'progressive' ]
			];
		} else {
			$buttonConfig += [
				'href' => SpecialPage::getTitleFor( 'ChangeEmail' )->getLinkURL( [
					'returnto' => $this->getContext()->getTitle()->getPrefixedText()
				] ),
				'flags' => [ 'primary', 'progressive' ],
			];
		}

		return new ButtonWidget( $buttonConfig );
	}

	/**
	 * @inheritDoc
	 */
	protected function getModules() {
		return 'ext.growthExperiments.Homepage.Email';
	}
}
