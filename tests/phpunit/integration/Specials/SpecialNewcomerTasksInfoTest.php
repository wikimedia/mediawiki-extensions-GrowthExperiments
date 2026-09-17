<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Tests\Integration;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\StaticTopicRegistry;
use GrowthExperiments\Specials\SpecialNewcomerTasksInfo;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use Psr\Log\LoggerInterface;

/**
 * @group Database
 * @covers \GrowthExperiments\Specials\SpecialNewcomerTasksInfo
 */
class SpecialNewcomerTasksInfoTest extends SpecialPageTestBase {
	protected function newSpecialPage(): SpecialNewcomerTasksInfo {
		$geServices = GrowthExperimentsServices::wrap( $this->getServiceContainer() );

		return new SpecialNewcomerTasksInfo(
			$geServices->getSuggestionsInfo(),
			$geServices->getFeatureManager(),
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

	/**
	 * Verify the newcomertasks info loads correctly
	 *
	 * Regression test for T438254.
	 *
	 * @covers \GrowthExperiments\NewcomerTasks\SuggestionsInfo::getInfo
	 * @covers \GrowthExperiments\NewcomerTasks\TaskSuggester\LocalSearchTaskSuggester
	 */
	public function testPageLoadsWithLocalSearchSuggester(): void {
		// FeatureManager needs WikimediaMessages. The service wiring selects the local
		// search suggester only when CirrusSearch is installed and is the search type.
		$this->markTestSkippedIfExtensionNotLoaded( 'WikimediaMessages' );
		$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		$this->overrideConfigValues( [
			'GEHomepageSuggestedEditsEnabled' => true,
			MainConfigNames::SearchType => 'CirrusSearch',
		] );
		// One topic keeps the number of searches small (and hardcodes names of topics).
		$this->setService( 'GrowthExperimentsTopicRegistry', new StaticTopicRegistry( [
			new OresBasedTopic( 'art', 'culture', [ 'painting' ] ),
		] ) );

		[ $html ] = $this->executeSpecialPage();

		// A test wiki has no search index, so the counts are not asserted. CirrusSearch
		// reports a search failure as a Status, which the page shows as a count of -1.
		$this->assertStringNotContainsString( '(newcomertasksinfo-no-data)', $html );
		$this->assertStringContainsString( '<code>copyedit</code>', $html );
		$this->assertStringContainsString( '<code>art</code>', $html );
	}
}
