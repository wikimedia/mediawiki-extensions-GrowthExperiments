<?php

namespace GrowthExperiments\Tests\Benchmark;

$path = dirname( __DIR__, 4 );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/includes/Setup.php';

require_once "GrowthExperimentsBench.php";

class LinkRecommendationFilterBench extends GrowthExperimentsBench {

	// phpcs:disable MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation

	/**
	 * @BeforeMethods ("setUpLinkRecommendation")
	 * @AfterMethods ("tearDownLinkRecommendation")
	 * @Assert("mode(variant.time.avg) < 100000 microseconds +/- 10%")
	 */
	public function benchFilter() {
		$this->linkRecommendationFilter->filter( $this->tasks );
	}

}
