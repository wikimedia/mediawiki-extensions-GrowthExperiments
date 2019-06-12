<?php
namespace GrowthExperiments\HomepageModules;

use IContextSource;
use OOUI\ButtonWidget;
use SpecialPage;

class Email extends BaseTaskModule {

	protected $emailState = null;

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'start-email', $context );

		$user = $this->getContext()->getUser();
		if ( $user->isEmailConfirmed() ) {
			$this->emailState = self::MODULE_STATE_CONFIRMED;
		} elseif ( $user->getEmail() ) {
			$this->emailState = self::MODULE_STATE_UNCONFIRMED;
		} else {
			$this->emailState = self::MODULE_STATE_NOEMAIL;
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
	protected function shouldInvertHeaderIcon() {
		return $this->isCompleted() || $this->emailState === self::MODULE_STATE_UNCONFIRMED;
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
		return $this->getContext()->msg( "growthexperiments-homepage-email-text-{$this->emailState}" )
			->params( $this->getContext()->getUser()->getName() )
			->escaped();
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
				'href' => SpecialPage::getTitleFor( 'Preferences', false, 'mw-prefsection-personal-email' )
					->getLinkURL()
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
