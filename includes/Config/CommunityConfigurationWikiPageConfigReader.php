<?php

namespace GrowthExperiments\Config;

use MediaWiki\Config\Config;

class CommunityConfigurationWikiPageConfigReader implements Config {
	public const MAP_POST_ON_TOP_VALUES = [
		'top' => true,
		'bottom' => false,
	];

	public const MAP_ASK_MENTOR_VALUES = [
		'mentor-talk-page' => true,
		'help-desk-page' => false,
	];

	private Config $wikiPageConfigReader;

	private Config $mainConfig;

	/**
	 * @param Config $wikiPageConfigReader
	 * @param Config $mainConfig
	 */
	public function __construct(
		Config $wikiPageConfigReader,
		Config $mainConfig
	) {
		$this->wikiPageConfigReader = $wikiPageConfigReader;
		$this->mainConfig = $mainConfig;
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function get( $name ) {
		$value = $this->wikiPageConfigReader->get( $name );
		if ( $name === 'GEHelpPanelHelpDeskPostOnTop' ) {
			$value = self::MAP_POST_ON_TOP_VALUES[$value];
		}
		if ( $name === 'GEHelpPanelAskMentor' ) {
			$value = self::MAP_ASK_MENTOR_VALUES[$value];
		}
		if ( $name === 'GELevelingUpKeepGoingNotificationThresholds' ) {
			$thresholds = $this->mainConfig->get( 'GELevelingUpKeepGoingNotificationThresholds' );
			$value = [ $thresholds[ 0 ], $value ];
		}
		return $value;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function has( $name ): bool {
		return $this->wikiPageConfigReader->has( $name );
	}
}
