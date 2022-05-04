<?php

namespace GrowthExperiments\NewcomerTasks;

use GrowthExperiments\VariantHooks;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;

/**
 * Wrapper for GECampaigns, used to retrieve campaign-specific information
 */
class CampaignConfig {

	/** @var array */
	private $config;

	/** @var array */
	private $topics;

	/** @var array */
	private $topicConfig;

	/** @var UserOptionsLookup|null */
	private $userOptionsLookup;

	/**
	 * @param array $config Campaign config
	 * @param array $topicConfig Mapping between topic ID and its search expression, used in
	 * 	PageConfigurationLoader to construct CampaignTopic
	 * @param UserOptionsLookup|null $userOptionsLookup
	 */
	public function __construct(
		array $config = [],
		array $topicConfig = [],
		UserOptionsLookup $userOptionsLookup = null
	) {
		$this->config = $config;
		$this->topicConfig = $topicConfig;
		$this->topics = array_unique( array_reduce( $config, static function ( $topics, $campaign ) {
			array_push( $topics, ...$campaign[ 'topics' ] ?? [] );
			return $topics;
		}, [] ) );
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * Get an array of mappings between topic ID and its search expression
	 *
	 * @return array
	 */
	public function getCampaignTopics(): array {
		$topicConfig = $this->topicConfig;
		if ( !count( $topicConfig ) ) {
			return [];
		}
		return array_reduce( $this->topics,
			static function ( $topics, $topicId ) use ( $topicConfig ) {
				if ( array_key_exists( $topicId, $topicConfig ) ) {
					$topics[] = [
						'id' => $topicId,
						'searchExpression' => $topicConfig[ $topicId ]
					];
				}
				return $topics;
			}, [] );
	}

	/**
	 * Get the topic IDs for users in the specified campaign
	 *
	 * @param string $campaign Name of the campaign
	 * @return array
	 */
	public function getTopicsForCampaign( string $campaign ): array {
		return $this->config[ $campaign ][ 'topics' ] ?? [];
	}

	/**
	 * Get the topic IDs to exclude for the user
	 *
	 * @param UserIdentity $user
	 * @return array
	 */
	public function getTopicsToExcludeForUser( UserIdentity $user ): array {
		$userCampaign = $this->userOptionsLookup->getOption(
			$user, VariantHooks::GROWTH_CAMPAIGN
		);
		if ( !$userCampaign ) {
			return $this->topics;
		}
		$campaign = $this->getCampaignIndexFromCampaignTerm( $userCampaign );
		return $this->getTopicsToExcludeForCampaign( $campaign );
	}

	/**
	 * Get the topic IDs to exclude for the specified campaign
	 *
	 * @param string|null $campaign
	 * @return array
	 */
	public function getTopicsToExcludeForCampaign( string $campaign = null ): array {
		if ( $campaign && array_key_exists( $campaign, $this->config ) ) {
			// Make sure topics shared between multiple campaigns aren't excluded
			return array_diff( $this->topics, $this->getTopicsForCampaign( $campaign ) );
		}
		return $this->topics;
	}

	/**
	 * Get the topic IDs to exclude for the specified campaign
	 *
	 * @param string $campaign
	 * @return ?string
	 */
	public function getCampaignPattern( string $campaign ): ?string {
		if ( array_key_exists( $campaign, $this->config ) ) {
			return $this->config[ $campaign ][ 'pattern' ];
		}
		return null;
	}

	/**
	 * Get the campaign name/index for the specified stored campaign term.
	 * @see VariantHooks::onLocalUserCreated()
	 *
	 * @param string $campaignTerm
	 * @return ?string
	 */
	public function getCampaignIndexFromCampaignTerm( string $campaignTerm ): ?string {
		$campaigns = array_filter( $this->config, static function ( $campaignConfig ) use ( $campaignTerm ) {
			return $campaignConfig[ 'pattern' ] && preg_match( $campaignConfig[ 'pattern' ], $campaignTerm );
		} );

		return array_key_first( $campaigns );
	}

	/**
	 * Check whether the user is in the specified campaign
	 *
	 * @param UserIdentity $user
	 * @param string $campaign
	 * @return bool
	 */
	public function isUserInCampaign( UserIdentity $user, string $campaign ): bool {
		$campaignPattern = $this->getCampaignPattern( $campaign );
		if ( $this->userOptionsLookup && $campaignPattern ) {
			$userCampaign = $this->userOptionsLookup->getOption(
				$user, VariantHooks::GROWTH_CAMPAIGN
			);
			return $userCampaign && preg_match( $campaignPattern, $userCampaign );
		}
		return false;
	}
}
