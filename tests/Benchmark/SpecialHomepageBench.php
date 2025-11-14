<?php

namespace GrowthExperiments\Tests\Benchmark;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialHomepage;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CommunityConfiguration\CommunityConfigurationServices;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

$path = dirname( __DIR__, 4 );

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/includes/Setup.php';

require_once "GrowthExperimentsBench.php";

class SpecialHomepageBench extends GrowthExperimentsBench {

	/** @var SpecialHomepage */
	private $homepage;

	public function setUp() {
		$services = MediaWikiServices::getInstance();
		$growthExperimentsServices = GrowthExperimentsServices::wrap( $services );
		$userOptionManager = $services->getUserOptionsManager();
		$communityConfigServices = CommunityConfigurationServices::wrap( MediaWikiServices::getInstance() );
		$this->homepage = new SpecialHomepage(
			$growthExperimentsServices->getHomepageModuleRegistry(),
			$services->getStatsFactory(),
			$growthExperimentsServices->getExperimentUserManager(),
			$growthExperimentsServices->getMentorManager(),
			$communityConfigServices->getMediaWikiConfigRouter(),
			$userOptionManager,
			$services->getTitleFactory(),
			$growthExperimentsServices->getReviseToneExperimentInteractionLogger(),
		);
		$context = new DerivativeContext( RequestContext::getMain() );
		$testUser = $services->getUserFactory()->newFromId( 1 );
		$context->setUser( $testUser );
		$userOptionManager->setOption( $testUser, 'growthexperiments-homepage-enable', 1 );
		$userOptionManager->saveOptions( $testUser );
		// Needed for StartEmail.php and other code that may look for the context title.
		$context->setTitle( SpecialPage::getTitleFor( 'Homepage' ) );
		$this->homepage->setContext( $context );
	}

	// phpcs:disable MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation

	/**
	 * @BeforeMethods ("setUp", "setUpLinkRecommendation" )
	 * @AfterMethods ("tearDownLinkRecommendation" )
	 * @Assert("mode(variant.time.avg) < 700000 microseconds +/- 10%")
	 */
	public function benchExecute() {
		$this->homepage->execute();
	}

}
