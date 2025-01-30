<?php

namespace GrowthExperiments\Tests\Integration;

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
	 * NOTE: We're marking this test as skipped since SpecialEditGrowthConfig
	 * is being deprecated in favor of the CommunityConfiguration extension.
	 * This entire test class will be removed once the migration is complete.
	 *
	 * @see T366139 for migration details
	 */
	protected function setUp(): void {
		parent::setUp();
		// SpecialEditGrowthConfig is a part of legacy CommunityConfiguration,
		// this test does not make sense with CC2.0.
		$this->overrideConfigValue( 'GEUseCommunityConfigurationExtension', false );
		$this->markTestSkipped(
			'SpecialEditGrowthConfig is deprecated in favor of CommunityConfiguration. ' .
			'This test will be removed as part of T366139.'
		);
	}

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
		$this->overrideConfigValues( $configOverrides );

		/** @var string $html */
		[ $html, ] = $this->executeSpecialPage();
		$this->assertNotEmpty( $html );
	}
}
