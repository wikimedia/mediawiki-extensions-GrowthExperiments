<?php

namespace GrowthExperiments\Maintenance;

use FormatJson;
use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\GrowthExperimentsServices;
use InvalidArgumentException;
use Maintenance;
use MediaWiki\MediaWikiServices;
use TitleFactory;

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

		$this->addArg(
			'key',
			'Config key that is updated (use . to separate keys in a multidimensional array)'
		);
		$this->addArg(
			'value',
			'New value of the config key'
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
		$configWriter = $this->initConfigWriter();

		$key = $this->getArg( 0 );
		if ( strpos( $key, '.' ) !== false ) {
			$key = explode( '.', $key );
		}
		$value = $this->getArg( 1 );
		if ( $this->hasOption( 'json' ) ) {
			$value = FormatJson::decode( $value, true );
		}
		try {
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
}

$maintClass = ChangeWikiConfig::class;
require_once RUN_MAINTENANCE_IF_MAIN;
