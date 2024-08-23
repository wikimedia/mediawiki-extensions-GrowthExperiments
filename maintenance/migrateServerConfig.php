<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\Config\WikiPageConfigWriter;
use GrowthExperiments\Config\WikiPageConfigWriterFactory;
use GrowthExperiments\GrowthExperimentsServices;
use LoggedUpdateMaintenance;
use MediaWiki\Title\TitleFactory;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script for migrating server config to on-wiki config files
 */
class MigrateServerConfig extends LoggedUpdateMaintenance {
	/** @var TitleFactory */
	private $titleFactory;

	/** @var WikiPageConfigWriterFactory */
	private $wikiPageConfigWriterFactory;

	/** @var WikiPageConfigWriter */
	private $wikiPageConfigWriter;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription( 'Migrate configuration to on-wiki config files from server config' );

		$this->addOption( 'dry-run', 'Print the configuration that would be saved on-wiki.' );
	}

	private function initServices() {
		$services = $this->getServiceContainer();

		$this->wikiPageConfigWriterFactory = GrowthExperimentsServices::wrap( $services )
			->getWikiPageConfigWriterFactory();
		$this->titleFactory = $services->getTitleFactory();
	}

	/**
	 * @inheritDoc
	 */
	protected function doDBUpdates() {
		$this->initServices();
		$dryRun = $this->hasOption( 'dry-run' );

		$title = $this->titleFactory->newFromText(
			$this->getConfig()->get( 'GEWikiConfigPageTitle' )
		);
		if ( $title === null ) {
			$this->fatalError( "Invalid GEWikiConfigPageTitle!\n" );
		}
		$this->wikiPageConfigWriter = $this->wikiPageConfigWriterFactory
			->newWikiPageConfigWriter(
				$title
			);

		$variables = [];
		foreach ( GrowthExperimentsMultiConfig::ALLOW_LIST as $variable ) {
			$variables[$variable] = $this->getConfig()->get( $variable );
		}
		if ( !$dryRun ) {
			$this->wikiPageConfigWriter->pruneConfig();
			$this->wikiPageConfigWriter->setVariables( $variables );
			$this->wikiPageConfigWriter->save(
				'Migrating server configuration to an on-wiki JSON file ([[phab:T275795]])'
			);
			$this->output( "Done!\n" );
			return true;
		} else {
			$this->output( json_encode( $variables, JSON_PRETTY_PRINT ) . "\n" );
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getUpdateKey() {
		return 'GrowthExperimentsMigrateServerConfig';
	}
}

$maintClass = MigrateServerConfig::class;
require_once RUN_MAINTENANCE_IF_MAIN;
