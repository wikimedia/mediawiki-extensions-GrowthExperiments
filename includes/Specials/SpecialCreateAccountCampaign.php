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
			'ext.growthExperiments.DonorSignupCampaign.styles',
		] );

		$benefitsList = '';
		if ( !$this->isRecurringDonorCampaign() ) {
			foreach ( [ 'lightbulb', 'mentor', 'difficulty-easy-bw' ] as $i => $icon ) {
				$index = $i + 1;
				$benefitsList .= Html::rawElement( 'li', [],
					new IconWidget( [ 'icon' => $icon ] )
					. Html::element( 'span', [],
						// The following message keys are used here:
						// * growthexperiments-signupcampaign-bullet1
						// * growthexperiments-signupcampaign-bullet2
						// * growthexperiments-signupcampaign-bullet3
						$this->msg( "growthexperiments-signupcampaign-bullet$index" )->text()
					)
				);
			}
			$benefitsList = Html::rawElement( 'ul', [ 'class' => 'mw-ge-donorsignup-list' ], $benefitsList );
		}

		$campaignKey = $this->getCampaignMessageKey();
		return Html::rawElement( 'div', [ 'class' => 'mw-createacct-benefits-container' ],
			Html::rawElement( 'div', [ 'class' => "mw-ge-donorsignup-block mw-ge-donorsignup-block-$campaignKey" ],
				Html::rawElement( 'h1', [ 'class' => 'mw-ge-donorsignup-title' ],
					// The following message keys are used here:
					// * growthexperiments-recurringcampaign-title
					// * growthexperiments-signupcampaign-title
					// * growthexperiments-josacampaign-title
					$this->msg( "growthexperiments-$campaignKey-title" )->parse()
				)
				. Html::rawElement( 'p', [ 'class' => 'mw-ge-donorsignup-body' ],
					// The following message keys are used here:
					// * growthexperiments-recurringcampaign-body
					// * growthexperiments-signupcampaign-body
					// * growthexperiments-josacampaign-body
					$this->msg( "growthexperiments-$campaignKey-body" )->parse()
				)
				. $benefitsList
			)
		);
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
	 * Check if the campaign field contains "recurring".
	 *
	 * @return string
	 */
	private function getCampaignMessageKey(): string {
		$campaign = $this->authForm->getField( 'campaign' )->getDefault();
		if ( strpos( $campaign, 'recurring' ) !== false ) {
			return 'recurringcampaign';
		} elseif ( strpos( $campaign, 'JOSA' ) !== false ) {
			return 'josacampaign';
		} else {
			return 'signupcampaign';
		}
	}

}
