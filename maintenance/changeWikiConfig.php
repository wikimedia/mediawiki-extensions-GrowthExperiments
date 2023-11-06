<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\GrowthExperimentsServices;
use InvalidArgumentException;
use Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ChangeWikiConfig extends Maintenance {
	/** @var TitleFactory */
	private $titleFactory;

	/** @var WikiPageConfigWriterFactory */
	private $wikiPageConfigWriterFactory;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Update a config key in on-wiki config' );

		$this->addOption(
			'json',
			'If true, input value will be treated as JSON, not as string'
		);
		$this->addOption(
			'page',
			'Page that will be changed (defaults to GEWikiConfigPageTitle)',
			false,
			true
		);
		$this->addOption(
			'summary',
			'Edit summary to use',
			false,
			true
		);
		$this->addOption(
			'touch',
			'Make a null edit to the page (useful to reflect config serialization changes); '
			. 'not supported for all config pages'
		);

		$this->addArg(
			'key',
			'Config key that is updated (use . to separate keys in a multidimensional array)',
			false
		);
		$this->addArg(
			'value',
			'New value of the config key',
			false
		);
		$this->addOption(
			'create-only',
			'Create the field if it doesn\'t exist but do not overwrite it if it does'
		);
	}

	private function initServices() {
		$services = MediaWikiServices::getInstance();

		$this->wikiPageConfigWriterFactory = GrowthExperimentsServices::wrap( $services )
			->getWikiPageConfigWriterFactory();
		$this->titleFactory = $services->getTitleFactory();
	}

	private function initConfigWriter(): WikiPageConfigWriter {
		$rawConfigPage = $this->getOption(
			'page',
			$this->getConfig()->get( 'GEWikiConfigPageTitle' )
		);
		$configPage = $this->titleFactory->newFromText( $rawConfigPage );
		if ( $configPage === null ) {
			$this->fatalError( "$rawConfigPage is not a valid title." );
		}

		try {
			'@phan-var \MediaWiki\Linker\LinkTarget $configPage';
			return $this->wikiPageConfigWriterFactory->newWikiPageConfigWriter(
				$configPage
			);
		} catch ( InvalidArgumentException $e ) {
			$this->fatalError( "$rawConfigPage is not a supported config page" );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->initServices();

		$touch = $this->hasOption( 'touch' );
		$key = $this->getArg( 0 );
		$value = $this->getArg( 1 );

		if ( !$touch && ( $key === null || $value === null ) ) {
			$this->fatalError( 'Key and value are required when --touch is not used.' );
		}

		if ( !$touch ) {
			$this->saveChange( $key, $value );
		} else {
			$this->touchConfigPage();
		}
	}

	/**
	 * Make a change to the config page
	 *
	 * @param mixed $key
	 * @param mixed $value
	 * @return void
	 */
	private function saveChange( $key, $value ) {
		$configWriter = $this->initConfigWriter();

		if ( strpos( $key, '.' ) !== false ) {
			$key = explode( '.', $key );
		}

		if ( $this->hasOption( 'json' ) ) {
			$status = FormatJson::parse( $value, FormatJson::FORCE_ASSOC );
			if ( !$status->isGood() ) {
				$this->fatalError(
					"Unable to decode JSON to use with $key: $value. Error from FormatJson::parse: " .
					$status->getWikiText( false, false, 'en' )
				);
			}
			$value = $status->getValue();
		}
		try {
			if ( $this->hasOption( 'create-only' )
				&& $configWriter->variableExists( $key )
			) {
				return;
			}
			$configWriter->setVariable( $key, $value );
		} catch ( InvalidArgumentException $e ) {
			$this->fatalError( $e->getMessage() );
		}

		$status = $configWriter->save( $this->getOption( 'summary', '' ) );
		if ( !$status->isOK() ) {
			$this->fatalError( $status->getWikiText() );
		}

		$this->output( "Saved!\n" );
	}

	/**
	 * @param string $rawPageA Raw page title
	 * @param string $rawPageB Raw page title
	 * @return bool
	 */
	private function rawPageTitleEquals( string $rawPageA, string $rawPageB ): bool {
		$pageA = $this->titleFactory->newFromText( $rawPageA );
		$pageB = $this->titleFactory->newFromText( $rawPageB );
		if ( $pageA === null || $pageB === null ) {
			return false;
		}
		return $pageA->equals( $pageB );
	}

	/**
	 * If supported, make a no-op change
	 */
	private function touchConfigPage() {
		$rawConfigPage = $this->getOption(
			'page',
			$this->getConfig()->get( 'GEWikiConfigPageTitle' )
		);

		if ( $this->rawPageTitleEquals( $rawConfigPage, $this->getConfig()->get( 'GEStructuredMentorList' ) ) ) {
			$statusValue = GrowthExperimentsServices::wrap( $this->getServiceContainer() )
				->getMentorWriter()
				->touchList(
					User::newSystemUser( 'Maintenance script', [ 'steal' => true ] ),
					''
				);
			if ( !$statusValue->isOK() ) {
				$this->fatalError( \Status::wrap( $statusValue )->getWikiText() );
			}
			$this->output( "Saved!\n" );
		} else {
			$this->fatalError( '--touch is not supported for ' . $rawConfigPage );
		}
	}
}

$maintClass = ChangeWikiConfig::class;
require_once RUN_MAINTENANCE_IF_MAIN;
