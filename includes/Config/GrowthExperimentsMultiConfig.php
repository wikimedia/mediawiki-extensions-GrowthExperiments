<?php

namespace GrowthExperiments\Config;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\Settings\Config\MergeStrategy;

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
class GrowthExperimentsMultiConfig implements Config, ICustomReadConstants {

	private WikiPageConfig $wikiPageConfig;
	private Config $globalVarConfig;

	// This should be in sync with SpecialEditGrowthConfig::getFormFields
	public const ALLOW_LIST = [
		'GEHelpPanelReadingModeNamespaces',
		'GEHelpPanelExcludedNamespaces',
		'GEHelpPanelHelpDeskTitle',
		'GEHelpPanelHelpDeskPostOnTop',
		'GEHelpPanelViewMoreTitle',
		'GEHelpPanelSearchNamespaces',
		'GEHelpPanelLinks',
		'GEHelpPanelAskMentor',
		'GEMentorshipEnabled',
		'GEHomepageSuggestedEditsIntroLinks',
		'GEInfoboxTemplates',
		'GEInfoboxTemplatesTest',
		'GECampaigns',
		'GECampaignTopics',
		'GEMentorshipAutomaticEligibility',
		'GEMentorshipMinimumAge',
		'GEMentorshipMinimumEditcount',
		'GEPersonalizedPraiseDefaultNotificationsFrequency',
		'GEPersonalizedPraiseDays',
		'GEPersonalizedPraiseMinEdits',
		'GEPersonalizedPraiseMaxEdits',
		'GEPersonalizedPraiseMaxReverts',
		'GELevelingUpGetStartedMaxTotalEdits'
	];

	/**
	 * Map of variable name => merge strategy. Defaults to replace.
	 * @see MergeStrategy
	 */
	public const MERGE_STRATEGIES = [
		'GECampaigns' => 'array_merge',
	];

	public function __construct(
		WikiPageConfig $wikiPageConfig,
		Config $globalVarConfig
	) {
		$this->wikiPageConfig = $wikiPageConfig;
		$this->globalVarConfig = $globalVarConfig;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	private function variableIsAllowed( $name ) {
		return in_array( $name, self::ALLOW_LIST );
	}

	/**
	 * Determine if on-wiki config is enabled or not
	 *
	 * If this returns false, all calls to get()/has() will be immediately
	 * forwarded to GlobalVarConfig, as if there was no on-wiki config.
	 *
	 * @return bool
	 */
	public function isWikiConfigEnabled(): bool {
		return (bool)$this->globalVarConfig->get( 'GEWikiConfigEnabled' );
	}

	/**
	 * @inheritDoc
	 */
	public function get( $name ) {
		return $this->getWithFlags( $name );
	}

	/**
	 * @param string $name
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return mixed Config value
	 */
	public function getWithFlags( $name, int $flags = 0 ) {
		if ( !$this->isWikiConfigEnabled() ) {
			return $this->globalVarConfig->get( $name );
		}

		if ( !$this->variableIsAllowed( $name ) ) {
			throw new ConfigException( 'Config key cannot be retrieved via GrowthExperimentsMultiConfig' );
		}

		if ( $this->wikiPageConfig->hasWithFlags( $name, $flags ) ) {
			$wikiValue = $this->wikiPageConfig->getWithFlags( $name, $flags );
			$mergeStrategy = self::MERGE_STRATEGIES[$name] ?? null;
			if ( !$mergeStrategy || !$this->globalVarConfig->has( $name ) ) {
				return $wikiValue;
			}
			$globalValue = $this->globalVarConfig->get( $name );
			return MergeStrategy::newFromName( $mergeStrategy )->merge( $globalValue, $wikiValue );
		} elseif ( $this->globalVarConfig->has( $name ) ) {
			return $this->globalVarConfig->get( $name );
		} else {
			throw new ConfigException( 'Config key was not found in GrowthExperimentsMultiConfig' );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function has( $name ) {
		return $this->hasWithFlags( $name );
	}

	/**
	 * @param string $name
	 * @param int $flags
	 * @return bool
	 */
	public function hasWithFlags( $name, int $flags = 0 ) {
		if ( !$this->isWikiConfigEnabled() ) {
			return $this->globalVarConfig->has( $name );
		}

		return $this->variableIsAllowed( $name ) && (
			$this->wikiPageConfig->hasWithFlags( $name, $flags ) ||
			$this->globalVarConfig->has( $name )
		);
	}
}
