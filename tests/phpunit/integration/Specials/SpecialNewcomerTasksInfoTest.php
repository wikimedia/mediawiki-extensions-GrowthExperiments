<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\Specials\SpecialNewcomerTasksInfo;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrowthExperiments\Specials\SpecialNewcomerTasksInfo
 */
class SpecialNewcomerTasksInfoTest extends SpecialPageTestBase {
	protected function newSpecialPage(): SpecialNewcomerTasksInfo {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		return new SpecialNewcomerTasksInfo(
			$geServices->getSuggestionsInfo(),
		);
	}

	public function testNoAccessToSuggestedEditsConfigIfDisabled(): void {
		$this->overrideConfigValue( 'GEHomepageSuggestedEditsEnabled', false );
		$this->overrideMwServices( null, [
			'GrowthExperimentsLogger' => fn () => $this->createNoOpMock(
				LoggerInterface::class
			),
		] );

		$this->executeSpecialPage();

		// Assertions are done by the NoOpMock
	}
}
