<?php

namespace GrowthExperiments\Specials;

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

		$donorHtml = $this->getDonorHtml();
		$formBlock = Html::rawElement( 'div', [ 'id' => 'userloginForm' ],
			$formHtml
		);
		$isMobile = $this->getSkin() instanceof SkinMinerva;
		$formAndDonor = $isMobile ? ( $donorHtml . $formBlock ) : ( $formBlock . $donorHtml );

		$html = Html::rawElement( 'div', [ 'class' => 'mw-ui-container' ],
			$loginPrompt
			. $signupStart
			. $formAndDonor
		);

		return $html;
	}

	/** @inheritDoc */
	protected function load( $subPage ) {
		// Remove the default Minerva "warning" that only serves aesthetic purposes but
		// do not remove real warnings.
		if ( $this->getSkin() instanceof SkinMinerva
			 && $this->getRequest()->getVal( 'warning' ) === 'mobile-frontend-generic-login-new'
		) {
			$this->getRequest()->setVal( 'warning', null );
		}

		parent::load( $subPage );
	}

	private function getDonorHtml(): string {
		if ( !$this->showExtraInformation() ) {
			return '';
		}

		$this->getOutput()->enableOOUI();
		$this->getOutput()->addModuleStyles( [
			'oojs-ui.styles.icons-interactions',
			'ext.growthExperiments.Homepage.icons',
			'ext.growthExperiments.donorSignupCampaign.styles',
		] );

		$list = '';
		foreach ( [ 'lightbulb', 'mentor', 'difficulty-easy-bw' ] as $i => $icon ) {
			$index = $i + 1;
			$list .= Html::rawElement( 'li', [],
				new IconWidget( [ 'icon' => $icon ] )
				. Html::element( 'span', [],
					$this->msg( "growthexperiments-signupcampaign-bullet$index" )->text()
				)
			);
		}

		return Html::rawElement( 'div', [ 'class' => 'mw-createacct-benefits-container' ],
			Html::rawElement( 'div', [ 'class' => 'mw-ge-donorsignup-block' ],
				Html::element( 'h1', [ 'class' => 'mw-ge-donorsignup-title' ],
					$this->msg( 'growthexperiments-signupcampaign-title' )->text()
				)
				. Html::element( 'p', [ 'class' => 'mw-ge-donorsignup-body' ],
					$this->msg( 'growthexperiments-signupcampaign-body' )->text()
				)
				. Html::rawElement( 'ul', [ 'class' => 'mw-ge-donorsignup-list' ], $list )
			)
		);
	}

}
