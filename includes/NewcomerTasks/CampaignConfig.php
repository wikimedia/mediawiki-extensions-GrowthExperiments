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
		return $this->getTopicsToExcludeForCampaign( $userCampaign );
	}

	/**
	 * Get the topic IDs to exclude for the specified campaign
	 *
	 * @param ?string $campaign
	 * @return array
	 */
	public function getTopicsToExcludeForCampaign( ?string $campaign = '' ): array {
		if ( $campaign && array_key_exists( $campaign, $this->config ) ) {
			// Make sure topics shared between multiple campaigns aren't excluded
			return array_diff( $this->topics, $this->getTopicsForCampaign( $campaign ) );
		}
		return $this->topics;
	}

	/**
	 * Check whether the user is in the specified campaign
	 *
	 * @param UserIdentity $user
	 * @param string $campaign
	 * @return bool
	 */
	public function isUserInCampaign( UserIdentity $user, string $campaign ): bool {
		if ( $this->userOptionsLookup && array_key_exists( $campaign, $this->config ) ) {
			$userCampaign = $this->userOptionsLookup->getOption(
				$user, VariantHooks::GROWTH_CAMPAIGN
			);
			return $userCampaign &&
				preg_match( $this->config[ $campaign ][ 'pattern' ], $userCampaign );
		}
		return false;
	}
}
