<?php

namespace GrowthExperiments;

use GrowthExperiments\NewcomerTasks\CampaignConfig;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\PostLoginRedirectHook;
use MediaWiki\Hook\SkinAddFooterLinksHook;
use MediaWiki\Hook\SpecialCreateAccountBenefitsHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderExcludeUserOptionsHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\Hook\UserGetDefaultOptionsHook;
use MediaWiki\User\Options\UserOptionsManager;
use Skin;

/**
 * Hooks related to feature flags used for A/B testing and opt-in.
 * At present only a single feature flag is handled.
 */
class VariantHooks implements
	AuthChangeFormFieldsHook,
	GetPreferencesHook,
	LocalUserCreatedHook,
	PostLoginRedirectHook,
	ResourceLoaderExcludeUserOptionsHook,
	ResourceLoaderGetConfigVarsHook,
	SkinAddFooterLinksHook,
	SpecialCreateAccountBenefitsHook,
	SpecialPageBeforeExecuteHook,
	UserGetDefaultOptionsHook
{
	/** Default A/B testing variant (control group). */
	public const VARIANT_CONTROL = 'control';
	/** A/B testing variant (treatment group) for the community-updates experiment. */
	public const VARIANT_COMMUNITY_UPDATES_MODULE = 'community-updates-module';
	public const VARIANT_NO_LINK_RECOMMENDATION = 'no-link-recommendation';

	/**
	 * This defines the allowed values for the variant preference. The default value is defined
	 * via $wgGEHomepageDefaultVariant.
	 */
	public const VARIANTS = [
		// 'A' doesn't exist anymore; was: not pre-initiated, impact module in main column,
		//     full size start module
		// 'B' doesn't exist anymore; was a pre-initiated version of A
		// 'C' doesn't exist anymore; was pre-initiated, impact module in side column,
		//     smaller start module
		// 'D' doesn't exist anymore; was not pre-initiated, onboarding embedded in suggested
		//     edits module, otherwise like C
		// 'linkrecommendation' Doesn't exist anymore. Opted users into the link-recommendation task type
		//     experiment; this is now default behavior for the control group.
		// 'imagerecommendation' Doesn't exist anymore. Opted users into the image-recommendation task type
		//     experiment; this is now default behavior for the control group.
		// 'oldimpact' Doesn't exist anymore. Was used for A/B testing the new Impact module.
		//     Removed as part of consolidating on the new Impact module implementation.
		//     See task T350077 for more details.
		self::VARIANT_COMMUNITY_UPDATES_MODULE,
		self::VARIANT_CONTROL,
		self::VARIANT_NO_LINK_RECOMMENDATION,
	];

	/** User option name for storing variants. */
	public const USER_PREFERENCE = 'growthexperiments-homepage-variant';
	/** @var string User option name for storing the campaign associated with account creation */
	public const GROWTH_CAMPAIGN = 'growthexperiments-campaign';

	private UserOptionsManager $userOptionsManager;
	private CampaignConfig $campaignConfig;
	private SpecialPageFactory $specialPageFactory;
	private Config $config;

	/**
	 * @param UserOptionsManager $userOptionsManager
	 * @param CampaignConfig $campaignConfig
	 * @param SpecialPageFactory $specialPageFactory
	 */
	public function __construct(
		UserOptionsManager $userOptionsManager,
		CampaignConfig $campaignConfig,
		SpecialPageFactory $specialPageFactory,
		Config $config
	) {
		$this->userOptionsManager = $userOptionsManager;
		$this->campaignConfig = $campaignConfig;
		$this->specialPageFactory = $specialPageFactory;
		$this->config = $config;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[self::USER_PREFERENCE] = [
			'type' => 'api',
		];
		$preferences[self::GROWTH_CAMPAIGN] = [
			'type' => 'api',
		];
	}

	/**
	 * Register defaults for variant-related preferences.
	 *
	 * @param array &$defaultOptions
	 */
	public function onUserGetDefaultOptions( &$defaultOptions ) {
		$defaultOptions += [
			self::USER_PREFERENCE => $this->config->get( 'GEHomepageDefaultVariant' ),
		];
	}

	/** @inheritDoc */
	public function onResourceLoaderExcludeUserOptions(
		array &$keysToExclude,
		RL\Context $context
	): void {
		$keysToExclude = array_merge( $keysToExclude, [
			self::GROWTH_CAMPAIGN,
		] );
	}

	// Note: we intentionally do not make $wgGEHomepageDefaultVariant the default value in the
	// UserGetDefaultOptions sense. That would result in the variant not being recorded if it's
	// the same as the default, and thus changing when the default is changed, and in an A/B test
	// we don't want that.

	/** @inheritDoc */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgGEUserVariants'] = self::VARIANTS;
		$vars['wgGEDefaultUserVariant'] = $config->get( 'GEHomepageDefaultVariant' );
	}

	/**
	 * Check if this is a Growth campaign by inspecting the campaign query parameter.
	 *
	 * @param string $campaignParameter
	 * @param CampaignConfig $campaignConfig
	 * @return bool
	 */
	public static function isGrowthCampaign(
		string $campaignParameter, CampaignConfig $campaignConfig
	): bool {
		if ( !$campaignParameter ) {
			return false;
		}

		return $campaignConfig->getCampaignIndexFromCampaignTerm( $campaignParameter ) !== null;
	}

	/**
	 * Check whether the welcome survey should be skipped by asking the $campaignConfig
	 * for the value given in the "campaign" query parameter
	 *
	 * @param string $campaign
	 * @param CampaignConfig $campaignConfig
	 * @return bool
	 */
	public static function shouldCampaignSkipWelcomeSurvey(
		string $campaign, CampaignConfig $campaignConfig
	): bool {
		return $campaign && $campaignConfig->shouldSkipWelcomeSurvey( $campaign );
	}

	/**
	 * Get the campaign from the user's saved options, falling back to the request parameter if
	 * the user's option isn't set. This is needed because the query parameter can get lost
	 * during CentralAuth redirection.
	 *
	 * @param IContextSource $context
	 * @return string
	 */
	public static function getCampaign( IContextSource $context ): string {
		$campaignFromRequestQueryParameter = $context->getRequest()->getVal( 'campaign', '' );
		if ( defined( 'MW_NO_SESSION' ) ) {
			// If we're in a ResourceLoader context, don't attempt to get the campaign string
			// from the user's preferences, as it's not allowed.
			return $campaignFromRequestQueryParameter;
		}

		$user = $context->getUser();
		if ( !$user->isSafeToLoad() ) {
			return $campaignFromRequestQueryParameter;
		}
		// URL parameter takes precedence if present
		if ( $campaignFromRequestQueryParameter !== '' ) {
			return $campaignFromRequestQueryParameter;
		}
		// fallback to user preference if no URL parameter exists
		return MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption(
			$user, self::GROWTH_CAMPAIGN,
			$campaignFromRequestQueryParameter
		);
	}

	/**
	 * Pass through the campaign flag for use by LocalUserCreated.
	 *
	 * @inheritDoc
	 */
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		$campaign = self::getCampaign( RequestContext::getMain() );
		// This is probably not strictly necessary; the Campaign extension sets this hidden field.
		// But if it's not there for whatever reason, add it here so we are sure it's available
		// in LocalUserCreated hook.
		if ( $campaign && !isset( $formDescriptor['campaign'] ) ) {
			$formDescriptor['campaign'] = [
				'type' => 'hidden',
				'name' => 'campaign',
				'default' => $campaign,
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( $autocreated || $user->isTemp() ) {
			return;
		}
		$context = RequestContext::getMain();
		if ( self::isGrowthCampaign( self::getCampaign( $context ), $this->campaignConfig ) ) {
			$this->userOptionsManager->setOption( $user, self::GROWTH_CAMPAIGN, $this->getCampaign( $context ) );
		}
	}

	/**
	 * Go directly to the homepage after signup if the user is in a campaign which has the
	 * "skip welcome survey" flag set.
	 * @inheritDoc
	 */
	public function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ) {
		if ( $type !== 'signup' ) {
			return;
		}
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			// Handled by onCentralAuthPostLoginRedirect
			return;
		}
		$context = RequestContext::getMain();
		if ( self::isGrowthCampaign( self::getCampaign( $context ), $this->campaignConfig )
			&& self::shouldCampaignSkipWelcomeSurvey( self::getCampaign( $context ), $this->campaignConfig )
		) {
			$returnTo = $this->specialPageFactory->getTitleForAlias( 'Homepage' )->getPrefixedText();
			$type = 'successredirect';
			return false;
		}
	}

	/**
	 * CentralAuth-compatible version of onPostLoginRedirect().
	 * @param string &$returnTo
	 * @param string &$returnToQuery
	 * @param bool $stickHTTPS
	 * @param string $type
	 * @param string &$injectedHtml
	 * @return bool|void
	 */
	public function onCentralAuthPostLoginRedirect(
		string &$returnTo, string &$returnToQuery, bool $stickHTTPS, string $type, string &$injectedHtml
	) {
		if ( $type !== 'signup' ) {
			return;
		}
		$context = RequestContext::getMain();
		if ( self::isGrowthCampaign( self::getCampaign( $context ), $this->campaignConfig )
			&& self::shouldCampaignSkipWelcomeSurvey( self::getCampaign( $context ), $this->campaignConfig )
		) {
			$returnTo = $this->specialPageFactory->getTitleForAlias( 'Homepage' )->getPrefixedText();
			$injectedHtml = '';
			return false;
		}
	}

	/** @inheritDoc */
	public function onSkinAddFooterLinks( Skin $skin, string $key, array &$footerItems ) {
		$context = $skin->getContext();
		if ( $key !== 'info' || !self::isGrowthCampaign( self::getCampaign( $context ), $this->campaignConfig ) ) {
			return;
		}
		$footerItems['signupcampaign-legal'] = CampaignBenefitsBlock::getLegalFooter( $context );
		$context->getOutput()->addModuleStyles( [ 'ext.growthExperiments.Account.styles' ] );
	}

	/** @inheritDoc */
	public function onSpecialCreateAccountBenefits( ?string &$html, array $info, array &$options ) {
		if ( !$this->shouldShowNewLandingPageHtml( $info['context'] ) ) {
			return true;
		}
		$options['beforeForm'] = $info['context']->getSkin() instanceof SkinMinerva;
		$benefitsBlock = new CampaignBenefitsBlock( $info['context'], $info['form'], $this->campaignConfig );
		$html = $benefitsBlock->getHtml();
		return false;
	}

	/**
	 * Check if the campaign field is set.
	 * @param IContextSource $context
	 * @return bool
	 */
	private function shouldShowNewLandingPageHtml( IContextSource $context ): bool {
		$campaignValue = $context->getRequest()->getRawVal( 'campaign' );
		$campaignName = $this->campaignConfig->getCampaignIndexFromCampaignTerm( $campaignValue );
		if ( $campaignName ) {
			$signupPageTemplate = $this->campaignConfig->getSignupPageTemplate( $campaignName );
			if ( in_array( $signupPageTemplate, [ 'hero', ], true ) ) {
				return true;
			} elseif ( $signupPageTemplate !== null ) {
				Util::logText( 'Unknown signup page template',
					[ 'campaign' => $campaignName, 'template' => $signupPageTemplate ] );
			}
		}
		return false;
	}

	/**
	 * Remove the default Minerva "warning" that only serves aesthetic purposes but
	 * do not remove real warnings.
	 * @inheritDoc
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		if ( $special->getName() === 'CreateAccount'
			&& $this->shouldShowNewLandingPageHtml( $special->getContext() )
			&& $special->getSkin() instanceof SkinMinerva
			&& $special->getRequest()->getVal( 'notice' ) === 'mobile-frontend-generic-login-new'
		) {
			$special->getRequest()->setVal( 'notice', null );
		}
	}
}
