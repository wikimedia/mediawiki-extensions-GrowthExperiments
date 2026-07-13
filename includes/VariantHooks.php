<?php

namespace GrowthExperiments;

use GrowthExperiments\Campaigns\CampaignLoader;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderExcludeUserOptionsHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\Skin\Hook\SkinAddFooterLinksHook;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\Hook\PostLoginRedirectHook;
use MediaWiki\Specials\Hook\SpecialCreateAccountBenefitsHook;
use MediaWiki\User\Options\UserOptionsManager;
use Wikimedia\Stats\StatsFactory;

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
	SpecialCreateAccountBenefitsHook
{

	/** @var string User option name for storing the campaign associated with account creation */
	public const GROWTH_CAMPAIGN = 'growthexperiments-campaign';

	public function __construct(
		private readonly UserOptionsManager $userOptionsManager,
		private readonly CampaignConfig $campaignConfig,
		private readonly Config $mainConfig,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly IExperimentManager $experimentManager,
		private readonly CampaignLoader $campaignLoader,
		private readonly FeatureManager $featureManager,
		private readonly StatsFactory $statsFactory,
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

		if ( $action === AuthManager::ACTION_CREATE ) {
			$this->recordBaseline( $campaign );
		}
	}

	private function recordBaseline( string $campaign ): void {
		$context = RequestContext::getMain();
		$user = $context->getUser();
		$skin = $context->getSkin();
		if ( $user === null || $user->isAnon() ) {
			$userType = 'anon';
		} elseif ( $user->isTemp() ) {
			$userType = 'temp';
		} else {
			$userType = 'named';
		}

		$campaignLabelToTrack = $this->getCampaignLabelForTracking( $campaign );
		$wikiName = $this->mainConfig->get( MainConfigNames::DBname );
		$isMobile = Util::isMobile( $skin );

		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getCounter( 'baseline_account_creation_forms_opened_total' )
			->setLabel( 'wiki', $wikiName )
			->setLabel( 'platform', $isMobile ? 'mobile' : 'desktop' )
			->setLabel( 'usertype', $userType )
			->setLabel( 'campaign', $campaignLabelToTrack )
			->increment();
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

		$this->recordAccountCreations( $campaign );
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

		if ( $this->featureManager->shouldShowCreateAccountV2(
			$info['context']->getUser(),
			$skin,
			$info['context']->getRequest()
		) ) {
			$html = '';
			return false;
		}

		if ( $this->featureManager->shouldShowCreateAccountNoBenefitsTreatment(
			$info['context']->getUser(),
			$skin,
			$info['context']->getRequest()
		) ) {
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

	private function recordAccountCreations( string $campaign ): void {
		$campaignLabelToTrack = $this->getCampaignLabelForTracking( $campaign );
		$context = RequestContext::getMain();
		$isMobile = Util::isMobile( $context->getSkin() );
		$hasEmail = $context->getRequest()->getVal( 'email', '' ) !== '';
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getCounter( 'account_creations_total' )
			->setLabel( 'wiki', $this->mainConfig->get( MainConfigNames::DBname ) )
			->setLabel( 'platform', $isMobile ? 'mobile' : 'desktop' )
			->setLabel( 'hasEmail', $hasEmail ? 'Yes' : 'No' )
			->setLabel( 'campaign', $campaignLabelToTrack )
			->increment();
	}

	private function getCampaignLabelForTracking( string $campaign ): string {
		if ( $campaign === '' ) {
			$campaignToTrack = 'none';
		} elseif ( $this->campaignConfig->isGrowthCampaign( $campaign ) ) {
			$campaignToTrack = $campaign;
		} else {
			$campaignToTrack = 'other';
		}
		return $campaignToTrack;
	}
}
