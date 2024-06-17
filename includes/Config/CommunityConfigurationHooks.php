<?php

namespace GrowthExperiments\Config;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Hooks\CommunityConfigurationSchemaBeforeEditorHook;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;

class CommunityConfigurationHooks implements CommunityConfigurationSchemaBeforeEditorHook {

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
				if ( !$this->config->get( 'GELevelingUpFeaturesEnabled' ) ) {
					unset(
						$rootSchema['properties']['GELevelingUpGetStartedMaxTotalEdits'],
						$rootSchema['properties']['GELevelingUpKeepGoingNotificationThresholds'],
					);
				}
				break;
		}
	}
}
