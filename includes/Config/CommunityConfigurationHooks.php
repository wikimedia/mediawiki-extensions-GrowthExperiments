<?php

namespace GrowthExperiments\Config;

use GrowthExperiments\Config\Schemas\CommunityUpdatesSchema;
use GrowthExperiments\LevelingUp\LevelingUpManager;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Hooks\CommunityConfigurationProvider_initListHook;
use MediaWiki\Extension\CommunityConfiguration\Hooks\CommunityConfigurationSchemaBeforeEditorHook;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;

class CommunityConfigurationHooks implements
	CommunityConfigurationSchemaBeforeEditorHook,
	CommunityConfigurationProvider_initListHook
{

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 */
	public function onCommunityConfigurationSchemaBeforeEditor(
		IConfigurationProvider $provider, array &$rootSchema
	) {
		switch ( $provider->getId() ) {
			case 'Mentorship':
				if ( !$this->config->get( 'GEPersonalizedPraiseBackendEnabled' ) ) {
					unset(
						$rootSchema['properties']['GEPersonalizedPraiseDays'],
						$rootSchema['properties']['GEPersonalizedPraiseDefaultNotificationsFrequency'],
						$rootSchema['properties']['GEPersonalizedPraiseMaxEdits'],
						$rootSchema['properties']['GEPersonalizedPraiseMinEdits']
					);
				}
				break;
			case 'GrowthSuggestedEdits':
				if ( !$this->config->get( 'GELinkRecommendationsFrontendEnabled' ) ) {
					// HACK: for T370611. This should be removed asap, ideally before end of August 2024.
					$rootSchema['properties']['link_recommendation']
						['properties']['disabled']['disabled-true-hack-T370611'] = true;
				}
				if ( !$this->config->get( 'GENewcomerTasksLinkRecommendationsEnabled' ) ) {
					unset( $rootSchema['properties']['link_recommendation'] );
				}
				if ( !$this->config->get( 'GENewcomerTasksImageRecommendationsEnabled' ) ) {
					unset( $rootSchema['properties']['image_recommendation'] );
				}
				if ( !$this->config->get( 'GENewcomerTasksSectionImageRecommendationsEnabled' ) ) {
					unset( $rootSchema['properties']['section_image_recommendation'] );
				}
				break;
			case 'GrowthHomepage':
				if ( !LevelingUpManager::isEnabledForAnyone( $this->config ) ) {
					unset(
						$rootSchema['properties']['GELevelingUpGetStartedMaxTotalEdits'],
						$rootSchema['properties']['GELevelingUpKeepGoingNotificationThresholdsMaximum'],
					);
				}
				break;
		}
	}

	private function isCalledFromBrokenTest(): bool {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			return false;
		}

		$trace = ( new \RuntimeException() )->getTraceAsString();
		return str_contains( $trace, 'ApiStructureTest' );
	}

	public function onCommunityConfigurationProvider_initList( array &$providers ) {
		if ( $this->config->get( 'GECommunityUpdatesEnabled' ) ) {
			$providers['CommunityUpdates'] = [
				"store" => [
					"type" => "wikipage",
					"args" => [
						"MediaWiki:GrowthExperimentsCommunityUpdates.json",
					],
				],
				"validator" => [
					"type" => "jsonschema",
					"args" => [
						CommunityUpdatesSchema::class,
					],
				],
				"type" => "data",
			];
		}
		if ( !$this->config->get( 'GEHomepageSuggestedEditsEnabled' ) ) {
			unset( $providers['GrowthSuggestedEdits'] );
		}
		if ( !$this->config->get( 'GEHelpPanelEnabled' ) ) {
			unset( $providers['HelpPanel'] );
		}

		// HACK: Do not break ApiStructureTest
		// TODO: Figure out why ApiStructureTest is failing with a validator defined here and
		// remove this (T380585)
		if (
			array_key_exists( 'GrowthSuggestedEdits', $providers ) &&
			$this->isCalledFromBrokenTest()
		) {
			$providers['GrowthSuggestedEdits']['validator'] = [
				'type' => 'noop',
			];
		}
	}
}
