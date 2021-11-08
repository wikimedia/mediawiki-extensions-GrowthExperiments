<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\HomepageHooks;
use Html;
use OOUI\IconWidget;
use SkinMinerva;
use SpecialCreateAccount;

/**
 * Customized version of SpecialCreateAccount with different landing text.
 * FIXME this is a quick hack for T284740. A proper extension point should be added to core.
 */
class SpecialCreateAccountCampaign extends SpecialCreateAccount {

	/** @inheritDoc */
	protected function getPageHtml( $formHtml ) {
		// This is copy-paste from the parent, except the middle code block is replaced by getDonorHtml()
		// and the order of the form and donor HTML depends on the device type. $wgLoginLanguageSelector
		// is ignored (T286587).

		$loginPrompt = $this->isSignup() ? '' : Html::rawElement( 'div',
			[ 'id' => 'userloginprompt' ], $this->msg( 'loginprompt' )->parseAsBlock() );
		$signupStartMsg = $this->msg( 'signupstart' );
		$signupStart = ( $this->isSignup() && !$signupStartMsg->isDisabled() )
			? Html::rawElement( 'div', [ 'id' => 'signupstart' ], $signupStartMsg->parseAsBlock() ) : '';

		$benefitsContainerHtml = $this->getBenefitsContainerHtml();
		$formBlock = Html::rawElement( 'div', [ 'id' => 'userloginForm' ],
			$formHtml
		);
		$isMobile = $this->getSkin() instanceof SkinMinerva;
		$formAndDonor = $isMobile ? ( $benefitsContainerHtml . $formBlock ) : ( $formBlock . $benefitsContainerHtml );

		return Html::rawElement( 'div', [ 'class' => 'mw-ui-container' ],
			$loginPrompt
			. $signupStart
			. $formAndDonor
		);
	}

	/** @inheritDoc */
	protected function getBenefitsContainerHtml(): string {
		return $this->shouldShowNewLandingPageHtml() ? $this->getDonorHtml() : parent::getBenefitsContainerHtml();
	}

	/** @inheritDoc */
	protected function load( $subPage ) {
		// Remove the default Minerva "warning" that only serves aesthetic purposes but
		// do not remove real warnings.
		if ( $this->shouldShowNewLandingPageHtml() && $this->getSkin() instanceof SkinMinerva
			 && $this->getRequest()->getVal( 'warning' ) === 'mobile-frontend-generic-login-new'
		) {
			$this->getRequest()->setVal( 'warning', null );
		}

		parent::load( $subPage );
	}

	/**
	 * @return string HTML to render in the CreateAccount form.
	 */
	private function getDonorHtml(): string {
		if ( !$this->showExtraInformation() ) {
			return '';
		}

		$this->getOutput()->enableOOUI();
		$this->getOutput()->addModuleStyles( [
			'oojs-ui.styles.icons-interactions',
			'ext.growthExperiments.icons',
			'ext.growthExperiments.donorSignupCampaign.styles',
		] );

		$msgKey = $this->isRecurringDonorCampaign() ? 'recurringcampaign' : 'signupcampaign';
		$list = '';
		foreach ( [ 'lightbulb', 'mentor', 'difficulty-easy-bw' ] as $i => $icon ) {
			$index = $i + 1;
			if ( $this->msg( "growthexperiments-$msgKey-bullet$index" )->exists() ) {
				$list .= Html::rawElement( 'li', [],
					new IconWidget( [ 'icon' => $icon ] )
					. Html::element( 'span', [],
						// The following message keys are used here:
						// * growthexperiments-recurringcampaign-bulletlightbulb
						// * growthexperiments-recurringcampaign-bulletmentor
						// * growthexperiments-recurringcampaign-bulletdifficulty-easy-bw
						// * growthexperiments-signupcampaign-bulletlightbulb
						// * growthexperiments-signupcampaign-bulletmentor
						// * growthexperiments-signupcampaign-bulletdifficulty-easy-bw
						$this->msg( "growthexperiments-$msgKey-bullet$index" )->text()
					)
				);
			}
		}

		return Html::rawElement( 'div', [ 'class' => 'mw-createacct-benefits-container' ],
			Html::rawElement( 'div', [ 'class' => 'mw-ge-donorsignup-block' ],
				Html::element( 'h1', [ 'class' => 'mw-ge-donorsignup-title' ],
					// The following message keys are used here:
					// * growthexperiments-recurringcampaign-title
					// * growthexperiments-signupcampaign-title
					$this->msg( "growthexperiments-$msgKey-title" )->text()
				)
				. Html::element( 'p', [ 'class' => 'mw-ge-donorsignup-body' ],
					// The following message keys are used here:
					// * growthexperiments-recurringcampaign-body
					// * growthexperiments-signupcampaign-body
					$this->msg( "growthexperiments-$msgKey-body" )->text()
				)
				. Html::rawElement( 'ul', [ 'class' => 'mw-ge-donorsignup-list' ], $list )
			)
		);
	}

	/**
	 * Check if the campaign field contains "recurring".
	 *
	 * @return bool
	 */
	private function isRecurringDonorCampaign(): bool {
		$campaign = $this->authForm->getField( 'campaign' )->getDefault();
		return strpos( $campaign, 'recurring' ) !== false;
	}

	/**
	 * Check if the campaign field is set and if the geNewLandingHtml field is true.
	 *
	 * @return bool
	 */
	private function shouldShowNewLandingPageHtml(): bool {
		$request = $this->getRequest();
		return $request->getCheck( 'campaign' )
			&& $request->getInt( HomepageHooks::REGISTRATION_GROWTHEXPERIMENTS_NEW_LANDING_HTML ) > 0;
	}

}
