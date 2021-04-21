<?php

namespace GrowthExperiments;

use Config;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;

/**
 * Hooks related to feature flags used for A/B testing and opt-in.
 * At present only a single feature flag is handled.
 */
class VariantHooks implements
	GetPreferencesHook,
	ResourceLoaderGetConfigVarsHook
{

	/**
	 * This defines the allowed values for the variant preference. The default value is defined
	 * via $wgGEHomepageDefaultVariant.
	 */
	public const VARIANTS = [
		// 'A' doesn't exist anymore; was: not pre-initiated, impact module in main column, full size start module
		// 'B' doesn't exist anymore; was a pre-initiated version of A
		// 'C' doesn't exist anymore; was pre-initiated, impact module in side column, smaller start module
		// not pre-initiated, onboarding embedded in suggested edits module, otherwise like C
		'D',
	];

	/** User option name for storing variants. */
	public const USER_PREFERENCE = 'growthexperiments-homepage-variant';

	/** @var Config */
	private $config;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences[self::USER_PREFERENCE] = [
			'type' => 'api',
		];
	}

	// Note: we intentionally do not make $wgGEHomepageDefaultVariant the default value in the
	// UserGetDefaultOptions sense. That would result in the variant not being recorded if it's
	// the same as the default, and thus changing when the default is changed, and in an A/B test
	// we don't want that.

	/** @inheritDoc */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgGEUserVariants'] = self::VARIANTS;
		$vars['wgGEDefaultUserVariant'] = $this->config->get( 'GEHomepageDefaultVariant' );
	}

}
