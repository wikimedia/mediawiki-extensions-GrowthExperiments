<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class InsertLinkRecommendation extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'GrowthExperiments' );
		$this->addDescription(
			'Insert a link recommendation from JSON file for a particular page. For testing and local development.'
		);
		$this->addOption( 'title', 'The title to insert a link recommendation for', true );
		$this->addOption( 'json-file', 'The JSON file containing the link recommendation data', true );
	}

	public function init() {
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		if ( !$growthServices->getGrowthConfig()->get( 'GEDeveloperSetup' ) ) {
			$this->fatalError( 'This script cannot be safely run in production.' );
		}
	}

	public function execute() {
		$this->init();
		$services = $this->getServiceContainer();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$json = file_get_contents( $this->getOption( 'json-file' ) );
		$data = json_decode( $json, true );
		if ( !$data ) {
			$this->fatalError( 'Unable to decode JSON' );
		}
		$title = $services->getTitleFactory()->newFromText( $this->getOption( 'title' ) );
		if ( !$title || !$title->exists() ) {
			$this->fatalError( 'Unable to get a title for ' . $this->getOption( 'title' ) );
		}

		$linkRecommendation = new LinkRecommendation(
			$title,
			$title->getId(),
			$title->getLatestRevID(),
			LinkRecommendation::getLinksFromArray( $data['links'] ),
			LinkRecommendation::getMetadataFromArray( $data['meta'] )
		);
		$this->output(
			'Inserting ' . count( $data['links'] ) . ' link recommendation(s) for ' . $title->getPrefixedText() . "\n"
		);
		$linkRecommendationStore = $growthServices->getLinkRecommendationStore();
		$linkRecommendationStore->insertExistingLinkRecommendation( $linkRecommendation );
	}
}

// @codeCoverageIgnoreStart
$maintClass = InsertLinkRecommendation::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
