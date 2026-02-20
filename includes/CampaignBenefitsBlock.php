<?php

namespace GrowthExperiments;

use GrowthExperiments\NewcomerTasks\CampaignConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MessageLocalizer;
use OOUI\IconWidget;
use Wikimedia\Assert\Assert;

/**
 * Customized version of the SpecialCreateAccount hero message, for campaigns.
 * Somewhat customizable via community configuration: the 'signupPageTemplate' and
 * 'signupPageTemplateParameters' parameters of the campaign configuration (see the
 * CampaignConfig class) will be passed to CampaignBenefitsBlock::getCampaignTemplateHtml.
 */
class CampaignBenefitsBlock {

	private IContextSource $context;
	private HTMLForm $authForm;
	private CampaignConfig $campaignConfig;

	public function __construct(
		IContextSource $context,
		HTMLForm $authForm,
		CampaignConfig $campaignConfig
	) {
		$this->context = $context;
		$this->authForm = $authForm;
		$this->campaignConfig = $campaignConfig;
	}

	/**
	 * Get footer content for the special page. Displayed via SkinAddFooterLinks hook.
	 * @param MessageLocalizer $ctx
	 * @return string|void
	 */
	public static function getLegalFooter( MessageLocalizer $ctx ) {
		return $ctx->msg( 'growthexperiments-campaigns-footer' )->parse();
	}

	/**
	 * @return string HTML to render on Special:CreateAccount.
	 */
	public function getHtml(): string {
		$campaignName = $this->campaignConfig->getCampaignIndexFromCampaignTerm( $this->getCampaignValue() );
		// If we got here, VariantHooks::shouldShowNewLandingPageHtml() is true
		// so there is a campaign with a template. Make phan happy.
		Assert::invariant( $campaignName !== null, '$campaignName is not null' );
		$template = $this->campaignConfig->getSignupPageTemplate( $campaignName );
		Assert::invariant( $template !== null, '$template is not null' );
		$parameters = $this->campaignConfig->getSignupPageTemplateParameters( $campaignName );

		// We only really have one template at this pont, with small variations.
		return $this->getCampaignTemplateHtml( $template, $parameters );
	}

	/**
	 * Known templates/parameters:
	 * - hero: welcome text with hero image
	 *   FIXME: the image should be parametrized (currently it's CSS+SVG)
	 *   - messageKey: used in th name of various messages:
	 *     - growthexperiments-{messageKey}-title: title text (h2)
	 *     - growthexperiments-{messageKey}-body: main welcome text
	 *     - growthexperiments-{messageKey}-title-mobile and
	 *       growthexperiments-{messageKey}-body-mobile: alternative text for mobile (to allow for
	 *       shorter text and avoid pushing the registration form below the fold). Disable (set to
	 *       '-') to not show anything; omit or blank to show the same text as on desktop.
	 *     - growthexperiments-{messageKey}-bullet1/2/3: three bullet items after the main text,
	 *       with the lightbulb, mentor and difficulty-easy-bw icons, meant to highlight Growth
	 *       features. Only used if showBenefitsList is true (but then all three are required).
	 *     Also used as a CSS class (.mw-ge-{messageKey}-block) for selecting a specific campaign.
	 *   - showBenefitsList: whether to show the benefit list (three bullet items highlighting
	 *     various Growth features), default false
	 * @param string $template
	 * @param array $parameters
	 * @return string
	 * @suppress PhanUnusedPrivateMethodParameter $template
	 */
	private function getCampaignTemplateHtml( $template, $parameters ) {
		$this->context->getOutput()->enableOOUI();
		$this->context->getOutput()->addModuleStyles( [
			'oojs-ui.styles.icons-interactions',
			'ext.growthExperiments.icons',
			'ext.growthExperiments.Account.styles',
		] );

		$this->context->getOutput()->addBodyClasses( 'mw-ge-customlandingpage' );

		$isMobile = $this->context->getSkin() instanceof SkinMinerva;
		$messageKey = $parameters['messageKey'];
		$shouldShowBenefitsList = $parameters['showBenefitsList'] ?? false;
		$shouldShowBenefitListInPlatform = $shouldShowBenefitsList === true ||
			( $shouldShowBenefitsList === 'desktop' && !$isMobile );
		$benefitsList = '';
		if ( $shouldShowBenefitListInPlatform ) {
			foreach ( [ 'lightbulb', 'mentor', 'difficulty-easy-bw' ] as $i => $icon ) {
				$index = $i + 1;
				$benefitMessage = $this->context->msg( "growthexperiments-$messageKey-bullet$index" );
				if ( !$benefitMessage->exists() ) {
					$benefitMessage = $this->context->msg( "growthexperiments-signupcampaign-bullet$index" );
				}
				$benefitsList .= Html::rawElement( 'li', [],
					new IconWidget( [ 'icon' => $icon ] )
					. Html::element( 'span', [],
						// The following message keys are used here:
						// * growthexperiments-signupcampaign-bullet1
						// * growthexperiments-signupcampaign-bullet2
						// * growthexperiments-signupcampaign-bullet3
						$benefitMessage->text()
					)
				);
			}
			$benefitsList = Html::rawElement( 'ul', [ 'class' => 'mw-ge-donorsignup-list' ], $benefitsList );
		}

		// The following message keys are used here:
		// * growthexperiments-recurringcampaign-title
		// * growthexperiments-signupcampaign-title
		// * growthexperiments-josacampaign-title
		// * growthexperiments-glamcampaign-title
		$titleMessage = $this->context->msg( "growthexperiments-$messageKey-title" );
		// The following message keys are used here:
		// * growthexperiments-recurringcampaign-body
		// * growthexperiments-signupcampaign-body
		// * growthexperiments-josacampaign-body
		// * growthexperiments-glamcampaign-body
		$bodyMessage = $this->context->msg( "growthexperiments-$messageKey-body" );

		$campaignTitle = '';
		$campaignBody = '';
		// note that a message consisting of a single dash is disabled but not blank
		if ( !$titleMessage->isDisabled() ) {
			$campaignTitle = Html::rawElement( 'h2', [ 'class' => 'mw-ge-donorsignup-title' ],
				$titleMessage->parse() );
		}
		if ( !$bodyMessage->isDisabled() ) {
			$campaignBody = Html::rawElement( 'p', [ 'class' => 'mw-ge-donorsignup-body' ],
				$bodyMessage->parse() );
		}
		return Html::rawElement( 'div', [ 'class' => 'mw-createacct-benefits-container' ],
			Html::rawElement( 'div', [ 'class' => "mw-ge-donorsignup-block mw-ge-donorsignup-block-$messageKey" ],
				$campaignTitle
				. $campaignBody
				. $benefitsList
			)
		);
	}

	/**
	 * Get the campaign from the account creation form
	 */
	private function getCampaignValue(): string {
		return $this->authForm->getField( 'campaign' )->getDefault();
	}
}
