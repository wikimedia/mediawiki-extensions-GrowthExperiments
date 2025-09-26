<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Maintenance;

use DirectoryIterator;
use ImportTextFiles;
use MediaWiki\Maintenance\Maintenance;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class PrepareBrowserTests extends Maintenance {
	public function execute(): void {
		$this->importPages();
		$this->importSubpages();
		$this->importLinkSuggestions();
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

	private function importSubpages(): void {
		$subpagesDirectory = __DIR__ . '/../cypress/support/setupFixtures/subpages/';
		$articleIterator = new DirectoryIterator( $subpagesDirectory );
		$subpagePaths = [];
		foreach ( $articleIterator as $directoryName ) {
			if ( $directoryName->isDot() ) {
				continue;
			}
			if ( !$directoryName->isDir() ) {
				throw new RuntimeException( 'Expected only directories in ' . $subpagesDirectory );
			}
			$articleTitle = $directoryName->getFilename();

			$subpageIterator = new DirectoryIterator( $subpagesDirectory . $articleTitle . '/' );
			foreach ( $subpageIterator as $subpageFilename ) {
				if ( $subpageFilename->isDot() ) {
					continue;
				}
				if ( $subpageFilename->isDir() ) {
					throw new RuntimeException( 'Expected only text files in ' . $directoryName );
				}
				$subpagePaths[] = $subpageFilename->getPathname();
			}

			$importScript = $this->createChild( ImportTextFiles::class );
			$importScript->loadParamsAndArgs(
				null,
				[
					'overwrite' => true,
					'rc' => true,
					'bot' => true,
					'prefix' => $articleTitle . '/',
				],
				$subpagePaths,
			);
			$importScript->execute();

			$subpagePaths = [];
		}
	}

	private function importLinkSuggestions(): void {
		$recommendationTitles = [
			'Douglas_Adams',
			'The_Hitchhiker\'s_Guide_to_the_Galaxy',
			'JR-430_Mountaineer',
		];
		$linkSuggestionsDirectory = __DIR__ . '/../cypress/support/setupFixtures/linkSuggestions/';
		foreach ( $recommendationTitles as $title ) {
			$importScript = $this->createChild(
				InsertLinkRecommendation::class,
				__DIR__ . '/insertLinkRecommendation.php',
			);
			$importScript->loadParamsAndArgs(
				null,
				[
					'title' => $title,
					'json-file' => $linkSuggestionsDirectory . $title . '.suggestions.json',
				]
			);
			$importScript->execute();
		}
	}

}

// @codeCoverageIgnoreStart
$maintClass = PrepareBrowserTests::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
