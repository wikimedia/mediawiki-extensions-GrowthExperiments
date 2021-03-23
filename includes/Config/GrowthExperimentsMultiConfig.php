<?php

namespace GrowthExperiments\Config;

use Config;
use ConfigException;
use GlobalVarConfig;
use MultiConfig;

/**
 * Config loader for wiki page config
 *
 * This class consults the allow list
 * in GrowthExperimentsMultiConfig::ALLOW_LIST, and runs
 * WikiPageConfig if requested config variable is there. Otherwise,
 * it throws an exception.
 *
 * Fallback to GlobalVarConfig is implemented, so developer setup
 * works without any config page, and also to not let wikis break
 * GE setup by removing an arbitrary config variable.
 */
class GrowthExperimentsMultiConfig implements Config {
	/** @var MultiConfig */
	private $multiConfig;

	public const ALLOW_LIST = [
		'GEHelpPanelReadingModeNamespaces',
		'GEHelpPanelExcludedNamespaces',
		'GEHelpPanelHelpDeskTitle',
		'GEHelpPanelHelpDeskPostOnTop',
		'GEHelpPanelViewMoreTitle',
		'GEHelpPanelSearchNamespaces',
		'GEHelpPanelAskMentor',
		'GEHomepageTutorialTitle',
		'GEMentorshipEnabled',
		'GEHomepageMentorsList',
		'GEHomepageManualAssignmentMentorsList',
		'GEHelpPanelSuggestedEditsPreferredEditor',
		'GEHomepageSuggestedEditsIntroLinks'
	];

	/**
	 * @param WikiPageConfig $wikiPageConfig
	 * @param GlobalVarConfig $globalVarConfig
	 */
	public function __construct(
		WikiPageConfig $wikiPageConfig,
		GlobalVarConfig $globalVarConfig
	) {
		$this->multiConfig = new MultiConfig( [
			$wikiPageConfig,
			$globalVarConfig
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function get( $name ) {
		if ( !$this->has( $name ) ) {
			throw new ConfigException( 'Config key was not found in GrowthExperimentsMultiConfig' );
		}

		return $this->multiConfig->get( $name );
	}

	/**
	 * @inheritDoc
	 */
	public function has( $name ) {
		return in_array( $name, self::ALLOW_LIST ) && $this->multiConfig->has( $name );
	}
}
