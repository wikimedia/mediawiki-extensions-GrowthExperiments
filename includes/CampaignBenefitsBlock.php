<?php

namespace GrowthExperiments;

use ExtensionRegistry;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\Util;
use Html;
use HTMLForm;
use IContextSource;
use MalformedTitleException;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MessageLocalizer;
use OOUI\IconWidget;
use OutputPage;
use Title;
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

	/**
	 * @param IContextSource $context
	 * @param HTMLForm $authForm
	 * @param CampaignConfig $campaignConfig
	 */
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
	 * - video: welcome text with video on top
	 *   - messageKey, showBenefitsList: as above
	 *   - file: video filename from Commons (without namespace)
	 *   - thumbtime: timestamp to use for still image for the video (default: leave it to MediaWiki)
	 * @param string $template
	 * @param array $parameters
	 * @return string
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
		$videoHtml = '';
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
		if ( $template === 'video' ) {
			$filename = $parameters['file'];
			$thumbtime = $parameters['thumbtime'] ?? null;
			$videoHtml = $this->getVideo( $this->context->getOutput(), $filename, $thumbtime );
		}

		// The following message keys are used here:
		// * growthexperiments-recurringcampaign-title
		// * growthexperiments-signupcampaign-title
		// * growthexperiments-josacampaign-title
		// * growthexperiments-glamcampaign-title
		// * growthexperiments-marketingvideocampaign-title
		$titleMessage = $this->context->msg( "growthexperiments-$messageKey-title" );
		// The following message keys are used here:
		// * growthexperiments-recurringcampaign-body
		// * growthexperiments-signupcampaign-body
		// * growthexperiments-josacampaign-body
		// * growthexperiments-glamcampaign-body
		// * growthexperiments-marketingvideocampaign-body
		$bodyMessage = $this->context->msg( "growthexperiments-$messageKey-body" );
		if ( $isMobile ) {
			// use mobile-specific title/body if they exist and aren't empty
			if ( !$this->context->msg( "growthexperiments-$messageKey-title-mobile" )->isBlank() ) {
				// The following message keys are used here:
				// none as of now
				$titleMessage = $this->context->msg( "growthexperiments-$messageKey-title-mobile" );
			}
			if ( !$this->context->msg( "growthexperiments-$messageKey-body-mobile" )->isBlank() ) {
				// The following message keys are used here:
				// * growthexperiments-marketingvideocampaign-body-mobile
				$bodyMessage = $this->context->msg( "growthexperiments-$messageKey-body-mobile" );
			}
		}

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
			. $videoHtml
		);
	}


	/**
	 * Get the campaign from the account creation form
	 *
	 * @return string
	 */
	private function getCampaignValue(): string {
		return $this->authForm->getField( 'campaign' )->getDefault();
	}

	/**
	 * Add a video player to the output.
	 *
	 * @param OutputPage $output Used te register required assets.
	 * @param string $filename Video file name (without the 'File:' prefix).
	 * @param int|null $thumbtime Optional time position for thumbnail generation, in seconds.
	 *   Theoretically a float, but non-integer support is broken: T228467
	 * @return string Video player HTML
	 */
	private function getVideo( OutputPage $output, string $filename, int $thumbtime = null ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'TimedMediaHandler' ) ) {
			Util::logText( 'TimedMediaHandler not loaded' );
			return '';
		}
		try {
			$title = Title::newFromTextThrow( 'File:' . $filename );
		} catch ( MalformedTitleException $e ) {
			Util::logText( $e->getMessage(), [ 'filename' => $filename ] );
			return '';
		}
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $title );
		if ( !$file ) {
			Util::logText( "File not found: $filename" );
			return '';
		}

		$output->addModules( [ 'ext.tmh.player' ] );
		$output->addModuleStyles( [ 'ext.tmh.player.styles' ] );

		$params = [];
		if ( Util::isMobile( $this->context->getSkin() ) ) {
			// For mobile, we don't know the width, so we pick a somewhat arbitrary height
			// to keep the controls for the video close to the thumbnail.
			$params['height'] = 200;
		} else {
			// Set same width as benefits container on desktop.
			$params['width'] = 400;
		}
		if ( $thumbtime !== null ) {
			$params['thumbtime'] = $thumbtime;
		}
		$html = Linker::makeImageLink(
			MediaWikiServices::getInstance()->getParser(),
			$title,
			$file,
			[ 'align' => 'center' ],
			$params
		);
		return Html::rawElement( 'div', [ 'class' => 'mw-ge-video' ], $html );
	}

}
