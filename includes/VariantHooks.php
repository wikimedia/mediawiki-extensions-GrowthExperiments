<?php

namespace GrowthExperiments;

use Config;
use GrowthExperiments\Specials\SpecialCreateAccountCampaign;
use IContextSource;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Hook\BeforeWelcomeCreationHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderExcludeUserOptionsHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\User\UserOptionsManager;
use RequestContext;
use ResourceLoaderContext;
use SpecialPage;

/**
 * Hooks related to feature flags used for A/B testing and opt-in.
 * At present only a single feature flag is handled.
 */
class VariantHooks implements
	GetPreferencesHook,
	ResourceLoaderExcludeUserOptionsHook,
	ResourceLoaderGetConfigVarsHook,
	SpecialPage_initListHook,
	LocalUserCreatedHook,
	AuthChangeFormFieldsHook,
	BeforeWelcomeCreationHook
{
	/** Default A/B testing variant (control group). */
	public const VARIANT_CONTROL = 'control';

	/** A/B testing variant with image recommendations enabled. */
	public const VARIANT_IMAGE_RECOMMENDATION_ENABLED = 'imagerecommendation';

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
		self::VARIANT_CONTROL,
		self::VARIANT_IMAGE_RECOMMENDATION_ENABLED,
	];

	/** User option name for storing variants. */
	public const USER_PREFERENCE = 'growthexperiments-homepage-variant';
	/** @var string User option name for storing the campaign associated with account creation */
	public const GROWTH_CAMPAIGN = 'growthexperiments-campaign';

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param UserOptionsManager $userOptionsManager
	 */
	public function __construct( UserOptionsManager $userOptionsManager ) {
		$this->userOptionsManager = $userOptionsManager;
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

	/** @inheritDoc */
	public function onResourceLoaderExcludeUserOptions(
		array &$keysToExclude,
		ResourceLoaderContext $context
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

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ) {
		// FIXME: Temporary hack for T284740, should be removed after the end of the campaign.
		$context = RequestContext::getMain();
		if ( self::isGrowthDonorCampaign( $context ) ) {
			$list['CreateAccount']['class'] = SpecialCreateAccountCampaign::class;
		}
	}

	/**
	 * Check if this is a Growth donor campaign by inspecting the campaign query parameter.
	 *
	 * @param IContextSource $context
	 * @return bool
	 */
	public static function isGrowthDonorCampaign( IContextSource $context ): bool {
		$geCampaignPattern = $context->getConfig()->get( 'GECampaignPattern' );
		$campaign = self::getCampaign( $context );
		return $geCampaignPattern
			&& $campaign
			// T285506: Don't assume the context has a title
			&& ( !$context->getTitle() || $context->getTitle()->isSpecial( 'CreateAccount' ) )
			&& preg_match( $geCampaignPattern, $campaign );
	}

	/**
	 * @param IContextSource $context
	 * @return string
	 * @codeCoverageIgnore
	 */
	private static function getCampaign( IContextSource $context ): string {
		return $context->getRequest()->getVal( 'campaign', '' );
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
	 * @suppress SecurityCheck-SQLInjection setOptions parameters are actually escaped (T290563?)
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		if ( $autocreated ) {
			return;
		}
		$context = RequestContext::getMain();
		if ( self::isGrowthDonorCampaign( $context ) ) {
			$this->userOptionsManager->setOption( $user, self::GROWTH_CAMPAIGN, $this->getCampaign( $context ) );
		}
	}

	/** @inheritDoc */
	public function onBeforeWelcomeCreation( &$welcome_creation_msg, &$injected_html ) {
		$context = RequestContext::getMain();
		if ( self::isGrowthDonorCampaign( $context ) ) {
			$context->getOutput()->redirect( SpecialPage::getSafeTitleFor( 'Homepage' )->getFullUrlForRedirect() );
		}
	}
}
