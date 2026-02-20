<?php

namespace GrowthExperiments\Config;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Access\MediaWikiConfigReader;
use MediaWiki\Extension\CommunityConfiguration\Access\MediaWikiConfigRouter;

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

	private MediaWikiConfigRouter $mediawikiConfigRouter;

	public function __construct(
		MediaWikiConfigRouter $mediaWikiConfigRouter
	) {
		$this->mediawikiConfigRouter = $mediaWikiConfigRouter;
	}

	/** @inheritDoc */
	public function get( $name ) {
		$value = $this->mediawikiConfigRouter->get( $name );
		if ( $name === 'GEHelpPanelHelpDeskPostOnTop' ) {
			$value = self::MAP_POST_ON_TOP_VALUES[$value];
		}
		if ( $name === 'GEHelpPanelAskMentor' ) {
			$value = self::MAP_ASK_MENTOR_VALUES[$value];
		}
		return $value;
	}

	/** @inheritDoc */
	public function has( $name ): bool {
		return $this->mediawikiConfigRouter->has( $name );
	}
}
