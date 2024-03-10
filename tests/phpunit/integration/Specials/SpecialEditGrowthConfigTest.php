<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Config\GrowthExperimentsMultiConfig;
use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialEditGrowthConfig;
use LogicException;
use SpecialPageTestBase;

/**
 * @coversDefaultClass \GrowthExperiments\Specials\SpecialEditGrowthConfig
 * @group Database
 */
class SpecialEditGrowthConfigTest extends SpecialPageTestBase {

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage() {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );
		$wikiConfig = $geServices->getGrowthWikiConfig();
		if ( !$wikiConfig instanceof GrowthExperimentsMultiConfig ) {
			throw new LogicException(
				'GrowthExperimentsMultiConfig service is expected to be an instance of GrowthExperimentsMultiConfig'
			);
		}
		return new SpecialEditGrowthConfig(
			$this->getServiceContainer()->getTitleFactory(),
			$this->getServiceContainer()->getRevisionLookup(),
			$this->getServiceContainer()->getPageProps(),
			$this->getServiceContainer()->getDBLoadBalancer(),
			$this->getServiceContainer()->getReadOnlyMode(),
			$geServices->getWikiPageConfigLoader(),
			$geServices->getWikiPageConfigWriterFactory(),
			$wikiConfig
		);
	}

	public static function provideConfigOverrides() {
		$overrides = [
			'default config' => [],
			'levelling up disabled' => [ 'GELevelingUpFeaturesEnabled' => false ],
		];
		foreach ( $overrides as $overrideName => $override ) {
			yield $overrideName => [ $override ];
		}
	}

	/**
	 * @covers ::execute
	 * @dataProvider provideConfigOverrides
	 */
	public function testExecutes( array $configOverrides ) {
		$this->setMwGlobals( $configOverrides );

		/** @var string $html */
		[ $html, ] = $this->executeSpecialPage();
		$this->assertNotEmpty( $html );
	}
}
