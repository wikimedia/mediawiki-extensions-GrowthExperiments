<?php

namespace GrowthExperiments\Config;

use MediaWiki\Config\Config;

class CommunityConfigurationWikiPageConfigReader implements Config {
	private const MAP_POST_ON_TOP_VALUES = [
		'top' => true,
		'bottom' => false,
	];

	private const MAP_ASK_MENTOR_VALUES = [
		'mentor-talk-page' => true,
		'help-desk-page' => false,
	];

	private Config $wikiPageConfigReader;

	/**
	 * @param Config $wikiPageConfigReader
	 */
	public function __construct( Config $wikiPageConfigReader ) {
		$this->wikiPageConfigReader = $wikiPageConfigReader;
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
