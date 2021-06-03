<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Util;
use Maintenance;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use PageProps;
use Title;
use TitleFactory;
use WikiMap;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class InitWikiConfig extends Maintenance {
	/** @var TitleFactory */
	private $titleFactory;

	/** @var PageProps */
	private $pageProps;

	/** @var WikiPageConfigWriterFactory */
	private $wikiPageConfigWriterFactory;

	/** @var WikiPageConfigWriter */
	private $wikiPageConfigWriter;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Initialize wiki configuration of GrowthExperiments based on Wikidata' );

		$this->addOption( 'dry-run', 'Print the configuration that would be saved on-wiki.' );
		$this->addOption( 'force', 'Skip validation (you should check the resulting config)' );
		$this->addOption(
			'phab',
			'ID of a Phabricator task about configuration of the wiki (e.q. T274646).' .
			'Will be linked in an edit summary',
			false,
			true
		);
		$this->addOption(
			'wikidata-wikiid',
			'Force wiki ID to be used by Wikidata (useful for localhost testing)',
			false,
			true
		);
	}

	private function initServices() {
		$services = MediaWikiServices::getInstance();

		$this->wikiPageConfigWriterFactory = GrowthExperimentsServices::wrap( $services )
			->getWikiPageConfigWriterFactory();
		$this->titleFactory = $services->getTitleFactory();
		$this->pageProps = $services->getPageProps();
		$this->httpRequestFactory = $services->getHttpRequestFactory();
	}

	/**
	 * @return string
	 */
	private function getWikidataWikiId() : string {
		return $this->hasOption( 'wikidata-wikiid' ) ?
			$this->getOption( 'wikidata-wikiid' ) :
			WikiMap::getCurrentWikiId();
	}

	/**
	 * @return array|false
	 * @throws \MWException
	 */
	private function getVariables() {
		// Init list of variables
		$variables = [];

		// Set help panel variables
		$variables['GEHelpPanelHelpDeskTitle'] = $this->getRawTitleFromWikidata( 'Q4026300' );
		$variables['GEHelpPanelLinks'] = array_filter( [
			// Manual of style
			$this->getHelpPanelLink( 'Q4994848' ),
			// Help:Editing
			$this->getHelpPanelLink(
				'Q151637',
				[],
				'mw:Special:MyLanguage/Help:VisualEditor/User_guide'
			),
			// Help:Introduction to images with VisualEditor
			$this->getHelpPanelLink(
				'Q27919584',
				[],
				'mw:Special:MyLanguage/Help:VisualEditor/User_guide#Images' ),
			// Help:Introduction to referencing with VisualEditor
			$this->getHelpPanelLink(
				'Q24238629',
				[],
				'mw:Special:MyLanguage/Help:VisualEditor/User_guide#Editing_references'
			),
			// Wikipedia:Article wizard
			$this->getHelpPanelLink( 'Q10968373' ),
		] );

		// Help:Contents
		$variables['GEHelpPanelViewMoreTitle'] = $this->getRawTitleFromWikidata( 'Q914807' );

		// Set suggested edits learn more links
		$variables['GEHomepageSuggestedEditsIntroLinks'] = array_filter( [
			// Wikipedia:Article wizard (preferred) or Help:How to start a new page
			'create' => $this->getRawTitleFromWikidata(
				'Q10968373',
				[ 'Q4966605' ]
			) ?? 'mw:Special:MyLanguage/Help:VisualEditor/User_guide',
			// Help:Introduction to images with VisualEditor
			'image' => $this->getRawTitleFromWikidata(
				'Q27919584'
			) ?? 'mw:Special:MyLanguage/Help:VisualEditor/User_guide#Images',
		] );

		// Set homepage variables
		// List of mentors, something like Wikipedia:Welcome/Signature
		$variables['GEHomepageMentorsList'] = $this->getRawTitleFromWikidata( 'Q14339834' );
		// List of mentors who can use Special:ClaimMentee (but aren't automatically assigned)
		$variables['GEHomepageManualAssignmentMentorsList'] = $this->
			getRawTitleFromWikidata( 'Q100973200' );

		// Remove null variables (array_filter will remove all variables which are not on Wikidata
		// as getRawTitleFromWikidata would return null in that case)
		$variables = array_filter( $variables );

		// Validate variables if --force was not used
		if ( !$this->hasOption( 'force' ) ) {
			$validationRes = $this->validateVariables( $variables );
			if ( is_string( $validationRes ) ) {
				$this->fatalError( $validationRes . "\n" );
				return false;
			}
		}

		return $variables;
	}

	private function getEditSummary() {
		$summary = 'Configuration for [[mw:Growth/Personalized first day/Newcomer homepage]].';
		if ( $this->hasOption( 'phab' ) ) {
			$summary .= ' See [[phab:' . $this->getOption( 'phab' ) . ']] for more information.';
		}
		return $summary;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();
		$dryRun = $this->hasOption( 'dry-run' );

		$title = $this->titleFactory->newFromText(
			$this->getConfig()->get( 'GEWikiConfigPageTitle' )
		);
		if ( $title === null ) {
			$this->fatalError( "Invalid GEWikiConfigPageTitle!\n" );
			return false;
		}
		$this->wikiPageConfigWriter = $this->wikiPageConfigWriterFactory
			->newWikiPageConfigWriter(
				$title
			);

		$variables = $this->getVariables();
		if ( $variables === false ) {
			// Error messages were printed in getVariables
			return false;
		}

		if ( !$dryRun ) {
			$this->wikiPageConfigWriter->setVariables( $variables );
			$this->wikiPageConfigWriter->save( $this->getEditSummary() );
			$url = $title->getFullURL();
			$this->output( "Done! Please review the config on $url\n" );
			return true;
		} else {
			$this->output( json_encode( $variables, JSON_PRETTY_PRINT ) . "\n" );
		}
	}

	/**
	 * @param array $variables
	 * @return true|string True on success, error message otherwise
	 */
	private function validateVariables( array $variables ) {
		if ( !array_key_exists( 'GEHomepageSuggestedEditsIntroLinks', $variables ) ) {
			return 'GEHomepageSuggestedEditsIntroLinks was not provided, please edit config manually';
		}

		foreach	( [ 'create', 'image' ] as $type ) {
			if ( !array_key_exists( $type, $variables['GEHomepageSuggestedEditsIntroLinks'] ) ) {
				return 'GEHomepageSuggestedEditsIntroLinks does not have one of mandatory links';
			}
		}

		return true;
	}

	/**
	 * Retreive entity data from Wikidata
	 *
	 * @param string $qid
	 * @return array|null
	 * @throws \MWException
	 */
	private function getWikidataData( string $qid ): ?array {
		$url = "https://www.wikidata.org/wiki/Special:EntityData/$qid.json";
		$status = Util::getJsonUrl( $this->httpRequestFactory, $url );
		if ( !$status->isOK() ) {
			$this->fatalError( 'Failed to download ' . $url . "\n" );
			return null;
		}
		return $status->getValue();
	}

	/**
	 * @param string $primaryQid
	 * @param string[] $backupQids
	 * @return string|null String on success, null on failure
	 * @throws \MWException
	 */
	private function getRawTitleFromWikidata(
		string $primaryQid,
		array $backupQids = []
	) : ?string {
		$qids = array_merge( [ $primaryQid ], $backupQids );
		foreach ( $qids as $qid ) {
			$data = $this->getWikidataData( $qid );
			if ( $data == null ) {
				$this->fatalError( "Wikidata returned an invalid JSON\n" );
				return null;
			}
			$sitelinks = $data['entities'][$qid]['sitelinks'];
			if ( array_key_exists( $this->getWikidataWikiId(), $sitelinks ) ) {
				return $sitelinks[$this->getWikidataWikiId()]['title'];
			}
		}

		return null;
	}

	/**
	 * Get help panel link ID to be used for given Title
	 *
	 * @note Similar code is used in SpecialEditGrowthConfig::normalizeHelpPanelLinks.
	 * @param Title $link
	 * @return string
	 */
	private function getHelpPanelLinkId( Title $link ) {
		$wdLinkId = null;
		if ( $link->exists() && !$link->isExternal() ) {
			$props = $this->pageProps->getProperties( $link, 'wikibase_item' );
			$pageId = $link->getId();
			if ( array_key_exists( $pageId, $props ) ) {
				$wdLinkId = $props[$pageId];
			}
		}
		return $wdLinkId ?? $link->getPrefixedDBkey();
	}

	/**
	 * @param string $primaryQid
	 * @param array $backupQids
	 * @param string|null $backupExternal Interwiki link to be used as link of last resort
	 * @return array|null
	 * @throws \MWException
	 */
	private function getHelpPanelLink(
		string $primaryQid,
		array $backupQids = [],
		?string $backupExternal = null
	) : ?array {
		$rawTitle = $this->getRawTitleFromWikidata( $primaryQid, $backupQids );
		if ( $rawTitle === null ) {
			if ( $backupExternal === null ) {
				return null;
			}
			$rawTitle = $backupExternal;
		}
		$title = $this->titleFactory->newFromText( $rawTitle );
		if ( $title === null ) {
			return null;
		}
		return [
			'title' => $title->getFullText(),
			'text' => $title->getText(),
			'id' => $this->getHelpPanelLinkId( $title )
		];
	}
}

$maintClass = InitWikiConfig::class;
require_once RUN_MAINTENANCE_IF_MAIN;
