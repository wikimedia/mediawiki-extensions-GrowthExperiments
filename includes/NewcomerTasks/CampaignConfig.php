<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\VariantHooks;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;

/**
 * Wrapper for the GECampaigns PHP / community configuration variable, used to retrieve
 * campaign-specific information.
 * The variable should be an array with entries in the format
 *
 *   <campaign index> => [
 *       'pattern' => <regex>,
 *       // ...other campaign settings
 *   ]
 *
 * The pattern is used to select the relevant entry based on the campaign name (which can be used
 * in the 'campaign' URL query parameter during registration, and will be recorded in user
 * preferences). The other settings can be used to select a landing page and modify the behavior
 * of various signup-related features.
 */
class CampaignConfig {

	public function __construct(
		private array $config = [],
		private ?UserOptionsLookup $userOptionsLookup = null
	) {
	}

	/**
	 * Get the name/index of the campaign matching the "campaign" query parameter value.
	 * In case of multiple matches the first one in the config array takes precedence.
	 * @see VariantHooks::onLocalUserCreated()
	 *
	 * @param string|null $campaignTerm The 'campaign' request parameter used at registration.
	 * @return string|null The campaign name, which is the array key used in $wgGECampaigns.
	 */
	public function getCampaignIndexFromCampaignTerm( ?string $campaignTerm ): ?string {
		if ( $campaignTerm === null ) {
			return null;
		}
		$campaigns = array_filter( $this->config, static function ( $campaignConfig ) use ( $campaignTerm ) {
			return $campaignConfig[ 'pattern' ] && preg_match( $campaignConfig[ 'pattern' ], $campaignTerm );
		} );
		return array_key_first( $campaigns );
	}

	/**
	 * Check if this is a Growth campaign by inspecting the campaign query parameter.
	 */
	public function isGrowthCampaign(
		string $campaignParameter
	): bool {
		if ( !$campaignParameter ) {
			return false;
		}

		return $this->getCampaignIndexFromCampaignTerm( $campaignParameter ) !== null;
	}

	/**
	 * Check whether the daily task limit should be skipped for the
	 * specified user
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	public function shouldSkipImageRecommendationDailyTaskLimitForUser( UserIdentity $user ): bool {
		$userCampaignPattern = $this->userOptionsLookup->getOption(
			$user, VariantHooks::GROWTH_CAMPAIGN
		);
		$userCampaign = $this->getCampaignIndexFromCampaignTerm( $userCampaignPattern );
		if ( !$userCampaign ) {
			return false;
		}
		return $this->shouldSkipImageRecommendationDailyTaskLimit( $userCampaign );
	}

	/**
	 * Check whether the welcome survey should be skipped for the
	 * specified campaign
	 *
	 * @param string $campaignTerm
	 * @return bool
	 */
	public function shouldSkipWelcomeSurvey( string $campaignTerm ): bool {
		if ( !$campaignTerm ) {
			return false;
		}
		$campaign = $this->getCampaignIndexFromCampaignTerm( $campaignTerm );
		return (bool)$this->getConfigValue( $campaign, 'skipWelcomeSurvey' );
	}

	/**
	 * Check whether the daily image recommendation task limit should be skipped for the
	 * specified campaign
	 *
	 * @param string $campaign
	 * @return bool
	 */
	public function shouldSkipImageRecommendationDailyTaskLimit( string $campaign ): bool {
		$qualityGateIdsToSkip = $this->getConfigValue( $campaign, 'qualityGateIdsToSkip' );
		if ( !$qualityGateIdsToSkip ) {
			return false;
		}
		if ( array_key_exists( 'image-recommendation', $qualityGateIdsToSkip ) ) {
			return in_array( 'dailyLimit', $qualityGateIdsToSkip[ 'image-recommendation' ] );
		}
		return false;
	}

	/**
	 * Get the message key value for the given campaign term
	 *
	 * @param string $campaignTerm
	 * @return string
	 */
	public function getMessageKey( string $campaignTerm ): string {
		$defaultMessageKey = 'signupcampaign';

		$campaign = $this->getCampaignIndexFromCampaignTerm( $campaignTerm );
		if ( !$campaign ) {
			return $defaultMessageKey;
		}

		return $this->getConfigValue( $campaign, 'messageKey' ) ?? $defaultMessageKey;
	}

	/**
	 * Get the value of the specified configuration index
	 *
	 * @param string|null $campaign
	 * @param string $campaignConfigurationIndex
	 * @return mixed|null
	 */
	private function getConfigValue( ?string $campaign, string $campaignConfigurationIndex ) {
		if ( $campaign === null ) {
			return null;
		}
		return $this->config[ $campaign ][ $campaignConfigurationIndex ] ?? null;
	}

}
