<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialEditGrowthConfig;
use GrowthExperiments\Util;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Page\PageProps;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class InitWikiConfig extends Maintenance {

	private TitleFactory $titleFactory;
	private PageProps $pageProps;
	private WikiPageConfigWriterFactory $wikiPageConfigWriterFactory;
	private HttpRequestFactory $httpRequestFactory;
	private ?SpecialEditGrowthConfig $specialEditGrowthConfig;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Initialize wiki configuration of GrowthExperiments based on Wikidata' );

		$this->addOption( 'dry-run', 'Print the configuration that would be saved on-wiki.' );
		$this->addOption( 'override', 'Override existing config files' );
		$this->addOption( 'skip-validation', 'Skip validation (you should check the resulting config)' );
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
		$services = $this->getServiceContainer();

		$this->wikiPageConfigWriterFactory = GrowthExperimentsServices::wrap( $services )
			->getWikiPageConfigWriterFactory();
		$this->titleFactory = $services->getTitleFactory();
		$this->pageProps = $services->getPageProps();
		$this->httpRequestFactory = $services->getHttpRequestFactory();
		$this->specialEditGrowthConfig = $services->getSpecialPageFactory()->getPage( 'EditGrowthConfig' );
	}

	private function getWikidataWikiId(): string {
		return $this->hasOption( 'wikidata-wikiid' ) ?
			$this->getOption( 'wikidata-wikiid' ) :
			WikiMap::getCurrentWikiId();
	}

	private function getEditSummary(): string {
		$summary = 'Configuration for [[mw:Growth/Personalized first day/Newcomer homepage]].';
		if ( $this->hasOption( 'phab' ) ) {
			$summary .= ' See [[phab:' . $this->getOption( 'phab' ) . ']] for more information.';
		}
		return $summary;
	}

	/**
	 * Retreive entity data from Wikidata
	 *
	 * @param string $qid
	 * @return array|null
	 */
	private function getWikidataData( string $qid ): ?array {
		$url = "https://www.wikidata.org/wiki/Special:EntityData/$qid.json";
		$status = Util::getJsonUrl( $this->httpRequestFactory, $url );
		if ( !$status->isOK() ) {
			$this->fatalError( 'Failed to download ' . $url . "\n" );
		}
		return $status->getValue();
	}

	/**
	 * @param string $primaryQid
	 * @param string[] $backupQids
	 * @return string|null String on success, null on failure
	 */
	private function getRawTitleFromWikidata(
		string $primaryQid,
		array $backupQids = []
	): ?string {
		$qids = array_merge( [ $primaryQid ], $backupQids );
		foreach ( $qids as $qid ) {
			$data = $this->getWikidataData( $qid );
			if ( $data == null ) {
				$this->fatalError( "Wikidata returned an invalid JSON\n" );
			}
			$sitelinks = $data['entities'][$qid]['sitelinks'];
			if ( array_key_exists( $this->getWikidataWikiId(), $sitelinks ) ) {
				return $sitelinks[$this->getWikidataWikiId()]['title'];
			}
		}

		return null;
	}

	private function getGEConfigVariables(): array {
		// Init list of variables
		$variables = [];

		// Set help panel variables
		$variables['GEHelpPanelHelpDeskTitle'] = $this->getRawTitleFromWikidata( 'Q4026300' );
		$variables['GEHelpPanelLinks'] = array_values( array_filter( [
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
			$this->getHelpPanelLink( 'Q10968373', [ 'Q4966605' ] ),
		] ) );

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

		// Remove null variables (array_filter will remove all variables which are not on Wikidata
		// as getRawTitleFromWikidata would return null in that case)
		$variables = array_filter( $variables );

		// Validate variables if --skip-validation was not used
		if ( !$this->hasOption( 'skip-validation' ) ) {
			$validationRes = $this->validateGEConfigVariables( $variables );
			if ( is_string( $validationRes ) ) {
				$this->fatalError( $validationRes . "\n" );
			}
		}

		return $variables;
	}

	/**
	 * @param array $variables
	 * @return true|string True on success, error message otherwise
	 */
	private function validateGEConfigVariables( array $variables ) {
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
	 */
	private function getHelpPanelLink(
		string $primaryQid,
		array $backupQids = [],
		?string $backupExternal = null
	): ?array {
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

	private function getSuggestedEditsVariables(): array {
		$taskTemplatesQIDs = [
			'copyedit' => [ 'Q6292692', 'Q6706206', 'Q6931087', 'Q7656698', 'Q6931386' ],
			'links' => [ 'Q13107723', 'Q5849007', 'Q5621858' ],
			'references' => [ 'Q5962027', 'Q6192879' ],
			'update' => [ 'Q5617874', 'Q14337093' ],
			'expand' => [ 'Q5529697', 'Q5623589', 'Q5866533' ],
		];
		$taskLearnMoreQIDs = [
			'copyedit' => [ 'Q10953805' ],
			'links' => [ 'Q27919580', 'Q75275496' ],
			'references' => [ 'Q79951', 'Q642335' ],
			'update' => [ 'Q4664141' ],
			'expand' => [ 'Q10973854', 'Q4663261' ],
		];
		$taskLearnMoreBackup = [
			'links' => 'mw:Special:MyLanguage/Help:VisualEditor/User_guide#Editing_links',
		];

		$variables = [];

		$defaultTaskTypeData = $this->specialEditGrowthConfig->getDefaultDataForEnabledTaskTypes();
		foreach ( $defaultTaskTypeData as $taskType => $taskData ) {
			if (
				!array_key_exists( $taskType, $taskTemplatesQIDs ) ||
				!array_key_exists( $taskType, $taskLearnMoreQIDs )
			) {
				continue;
			}

			$templates = [];
			foreach ( $taskTemplatesQIDs[$taskType] as $qid ) {
				$candidateTitle = $this->getRawTitleFromWikidata( $qid );
				if ( $candidateTitle === null ) {
					continue;
				}
				$templates[] = $this->titleFactory->newFromText( $candidateTitle )->getText();
			}

			if ( $templates === [] ) {
				continue;
			}

			$variables[$taskType] = [
				'group' => $taskData['difficulty'],
				'templates' => $templates,
			];

			$learnmoreLink = $this->getRawTitleFromWikidata(
				$taskLearnMoreQIDs[$taskType][0],
				array_slice( $taskLearnMoreQIDs[$taskType], 1 )
			);
			if ( $learnmoreLink === null && array_key_exists( $taskType, $taskLearnMoreBackup ) ) {
				$learnmoreLink = $taskLearnMoreBackup[$taskType];
			}
			if ( $learnmoreLink !== null ) {
				$variables[$taskType]['learnmore'] = $learnmoreLink;
			}
		}
		return $variables;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();
		$dryRun = $this->hasOption( 'dry-run' );

		return $this->initGEConfig( $dryRun ) &&
			$this->initSuggestedEditsConfig( $dryRun );
	}

	/**
	 * @param bool $dryRun
	 * @return bool
	 */
	private function initGEConfig( $dryRun ) {
		$title = $this->titleFactory->newFromText(
			$this->getConfig()->get( 'GEWikiConfigPageTitle' )
		);
		if ( $title === null ) {
			$this->fatalError( "Invalid GEWikiConfigPageTitle!\n" );
		}
		if (
			!$this->hasOption( 'override' ) &&
			!$this->hasOption( 'dry-run' ) &&
			$title->exists()
		) {
			$this->fatalError(
				"On-wiki config already exists ({$title->getPrefixedText()}). " .
				"You can skip the validation using --override."
			);
		}

		// @phan-suppress-next-next-line PhanTypeMismatchArgumentNullable Still T240141?
		$wikiPageConfigWriter = $this->wikiPageConfigWriterFactory
			->newWikiPageConfigWriter( $title );

		$variables = $this->getGEConfigVariables();
		if ( !$dryRun ) {
			$wikiPageConfigWriter->setVariables( $variables );
			$status = $wikiPageConfigWriter->save( $this->getEditSummary() );
			if ( !$status->isOK() ) {
				$this->fatalError( $status->getWikiText( false, false, 'en' ) );
			}
		} else {
			$this->output( $title->getPrefixedText() . ":\n" );
			$this->output( json_encode( $variables, JSON_PRETTY_PRINT ) . "\n" );
		}

		return true;
	}

	private function initSuggestedEditsConfig( bool $dryRun ): bool {
		$title = $this->titleFactory->newFromText(
			$this->getConfig()->get( 'GENewcomerTasksConfigTitle' )
		);
		if ( $title === null ) {
			$this->fatalError( "Invalid GENewcomerTasksConfigTitle!\n" );
		}
		if (
			!$this->hasOption( 'override' ) &&
			!$this->hasOption( 'dry-run' ) &&
			$title->exists()
		) {
			$this->fatalError(
				"On-wiki config already exists ({$title->getPrefixedText()}). " .
				"You can skip the validation using --override."
			);
		}

		// @phan-suppress-next-next-line PhanTypeMismatchArgumentNullable Still T240141?
		$wikiPageConfigWriter = $this->wikiPageConfigWriterFactory
			->newWikiPageConfigWriter( $title );

		$variables = $this->getSuggestedEditsVariables();

		if ( !$dryRun ) {
			$wikiPageConfigWriter->setVariables( $variables );
			$status = $wikiPageConfigWriter->save( $this->getEditSummary() );
			if ( !$status->isOK() ) {
				$this->fatalError( $status->getWikiText( false, false, 'en' ) );
			}
			return true;
		} else {
			$this->output( $title->getPrefixedText() . ":\n" );
			$this->output( json_encode( $variables, JSON_PRETTY_PRINT ) . "\n" );
			return false;
		}
	}
}

$maintClass = InitWikiConfig::class;
require_once RUN_MAINTENANCE_IF_MAIN;
