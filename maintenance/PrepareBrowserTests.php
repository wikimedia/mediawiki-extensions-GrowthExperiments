<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Maintenance;

use ImportTextFiles;
use MediaWiki\Maintenance\Maintenance;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PrepareBrowserTests extends Maintenance {
	public function execute(): void {
		$this->importPages();
	}

	private function importPages(): void {
		$pages = [];
		$pagesDirectory = __DIR__ . '/../cypress/support/setupFixtures/pages/';
		$recursiveIteratorIterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $pagesDirectory ),
		);
		foreach ( $recursiveIteratorIterator as $filename ) {
			if ( $filename->isDir() ) {
				continue;
			}

			$pages[] = $filename->getPathname();
		}

		$importScript = $this->createChild( ImportTextFiles::class );
		$importScript->loadParamsAndArgs(
			null,
			[
				'overwrite' => true,
				'rc' => true,
				'bot' => true,
			],
			$pages,
		);
		$importScript->execute();
	}
}

$maintClass = PrepareBrowserTests::class;
require_once RUN_MAINTENANCE_IF_MAIN;
