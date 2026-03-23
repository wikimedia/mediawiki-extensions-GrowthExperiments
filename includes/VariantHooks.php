<?php

namespace GrowthExperiments;

use GrowthExperiments\Campaigns\CampaignLoader;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\PostLoginRedirectHook;
use MediaWiki\Hook\SkinAddFooterLinksHook;
use MediaWiki\Hook\SpecialCreateAccountBenefitsHook;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderExcludeUserOptionsHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\Options\UserOptionsManager;

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
	SpecialPageBeforeExecuteHook
{

	/** @var string User option name for storing the campaign associated with account creation */
	public const GROWTH_CAMPAIGN = 'growthexperiments-campaign';

	public function __construct(
		private readonly UserOptionsManager $userOptionsManager,
		private readonly CampaignConfig $campaignConfig,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly IExperimentManager $experimentManager,
		private readonly CampaignLoader $campaignLoader,
		private readonly FeatureManager $featureManager,
	) {
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[self::GROWTH_CAMPAIGN] = [
			'type' => 'api',
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

	/** @inheritDoc */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		if ( $this->experimentManager instanceof StaticExperimentManager ) {
			$vars['wgGEDefaultUserVariant'] = $config->get( 'GEHomepageDefaultVariant' );
		}
	}

	/**
	 * Pass through the campaign flag for use by LocalUserCreated.
	 *
	 * @inheritDoc
	 */
	public function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		$campaign = $this->campaignLoader->getCampaign();
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

		$campaign = $this->campaignLoader->getCampaign();
		if ( $this->campaignConfig->isGrowthCampaign( $campaign ) ) {
			$this->userOptionsManager->setOption( $user, self::GROWTH_CAMPAIGN, $campaign );
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

		$campaign = $this->campaignLoader->getCampaign();
		if ( $this->campaignConfig->isGrowthCampaign( $campaign )
			&& $this->campaignConfig->shouldSkipWelcomeSurvey( $campaign )
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

		$campaign = $this->campaignLoader->getCampaign();
		if ( $this->campaignConfig->isGrowthCampaign( $campaign )
			&& $this->campaignConfig->shouldSkipWelcomeSurvey( $campaign )
		) {
			$returnTo = $this->specialPageFactory->getTitleForAlias( 'Homepage' )->getPrefixedText();
			$injectedHtml = '';
			$returnToQueryArray = wfCgiToArray( $returnToQuery );
			$returnToQueryArray['accountJustCreated'] = 1;
			$returnToQuery = wfArrayToCgi( $returnToQueryArray );
			return false;
		}
	}

	/** @inheritDoc */
	public function onSkinAddFooterLinks( Skin $skin, string $key, array &$footerItems ) {
		$context = $skin->getContext();
		if (
			$key !== 'info' ||
			!$this->campaignConfig->isGrowthCampaign( $this->campaignLoader->getCampaign() )
		) {
			return;
		}
		$footerItems['signupcampaign-legal'] = CampaignBenefitsBlock::getLegalFooter( $context );
		$context->getOutput()->addModuleStyles( [ 'ext.growthExperiments.Account.styles' ] );
	}

	/** @inheritDoc */
	public function onSpecialCreateAccountBenefits( ?string &$html, array $info, array &$options ) {
		$skin = $info['context']->getSkin();

		if ( $this->featureManager->shouldShowCreateAccountV1( $info['context']->getUser(), $skin ) ) {
			$html = '';
			return false;
		}

		if ( $this->shouldShowNewLandingPageHtml( $info['context'] ) ) {
			// campaign
			$options['beforeForm'] = $skin instanceof SkinMinerva;
			$benefitsBlock = new CampaignBenefitsBlock( $info['context'], $info['form'], $this->campaignConfig );
			$html = $benefitsBlock->getHtml();
			return false;
		}

		return true;
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
			if ( in_array( $signupPageTemplate, [ 'hero' ], true ) ) {
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
		if ( $special->getName() !== 'CreateAccount'
			|| !$special->getSkin() instanceof SkinMinerva
		) {
			return;
		}

		$context = $special->getContext();
		if (
			$this->shouldShowNewLandingPageHtml( $context ) ||
			$this->featureManager->shouldShowCreateAccountV1( $context->getUser(), $context->getSkin() )
		) {
			if ( $special->getRequest()->getVal( 'notice' ) === 'mobile-frontend-generic-login-new' ) {
				$special->getRequest()->setVal( 'notice', null );
			}
		}
	}
}
