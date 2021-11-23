<?php

namespace GrowthExperiments\Maintenance;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use Maintenance;
use MediaWiki\MediaWikiServices;

$path = dirname( dirname( dirname( __DIR__ ) ) );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';

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
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		if ( !$growthServices->getGrowthConfig()->get( 'GEDeveloperSetup' ) ) {
			$this->fatalError( 'This script cannot be safely run in production.' );
		}
	}

	public function execute() {
		$this->init();
		$services = MediaWikiServices::getInstance();
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$json = file_get_contents( $this->getOption( 'json-file' ) );
		$data = json_decode( $json, true );
		if ( !$data ) {
			$this->fatalError( 'Unable to decode JSON' );
		}
		$title = $services->getTitleFactory()->newFromText( $this->getOption( 'title' ) );
		if ( !$title ) {
			$this->fatalError( 'Unable to get a title for ' . $this->getOption( 'title' ) );
		}
		$linkRecommendation = new LinkRecommendation(
			$title,
			$title->getId(),
			$title->getLatestRevID(),
			LinkRecommendation::getLinksFromArray( $data['links'] ),
			LinkRecommendation::getMetadataFromArray( $data['meta'] )
		);
		$linkRecommendationStore = $growthServices->getLinkRecommendationStore();
		$linkRecommendationStore->insert( $linkRecommendation );
	}
}

$maintClass = InsertLinkRecommendation::class;
require_once RUN_MAINTENANCE_IF_MAIN;
