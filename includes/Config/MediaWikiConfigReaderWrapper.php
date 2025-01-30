<?php

namespace GrowthExperiments\Config;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Access\MediaWikiConfigReader;

/**
 * Wrapper around MediaWikiConfigReader from CommunityConfiguration, in order
 * to modify configuration before passing it to GrowthExperiments if needed.
 *
 * @see MediaWikiConfigReader
 */
class MediaWikiConfigReaderWrapper implements Config {
	public const MAP_POST_ON_TOP_VALUES = [
		'top' => true,
		'bottom' => false,
	];

	public const MAP_ASK_MENTOR_VALUES = [
		'mentor-talk-page' => true,
		'help-desk-page' => false,
	];

	private Config $mediawikiConfigReader;

	private Config $mainConfig;

	public function __construct(
		Config $mediawikiConfigReader,
		Config $mainConfig
	) {
		$this->mediawikiConfigReader = $mediawikiConfigReader;
		$this->mainConfig = $mainConfig;
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function get( $name ) {
		$value = $this->mediawikiConfigReader->get( $name );
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
		return $this->mediawikiConfigReader->has( $name );
	}
}
