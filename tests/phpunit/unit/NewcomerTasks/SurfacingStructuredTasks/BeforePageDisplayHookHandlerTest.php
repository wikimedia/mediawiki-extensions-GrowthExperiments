<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\SurfacingStructuredTasks\BeforePageDisplayHookHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Config\HashConfig;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skins\Vector\SkinVector22;
use MediaWiki\Skins\Vector\SkinVectorLegacy;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Skin;

/**
 * @covers \GrowthExperiments\NewcomerTasks\SurfacingStructuredTasks\BeforePageDisplayHookHandler
 */
class BeforePageDisplayHookHandlerTest extends MediaWikiUnitTestCase {

	public function testLoadsModuleAndAddsConfigData(): void {
		$config = $this->getConfig();
		$configurationLoader = $this->getConfigurationLoader();
		$skin = $this->getStubSkin();
		$mockOutputPage = $this->getMockOutputPage();

		$mockOutputPage
			->expects( $this->once() )
			->method( 'addJsConfigVars' )
			->with( 'wgGrowthExperimentsLinkRecommendationTask', [
				'maxLinks' => 3,
				'minScore' => 0.6,
			] );
		$mockOutputPage
			->expects( $this->once() )
			->method( 'addModules' )
			->with( 'ext.growthExperiments.StructuredTask.Surfacing' );

		$hookHandler = new BeforePageDisplayHookHandler( $config, $configurationLoader );
		$hookHandler->onBeforePageDisplay( $mockOutputPage, $skin );
	}

	public static function provideNoSurfacingTaskScenarios(): iterable {
		yield 'Surfacing Structured Tasks disabled in config' => [
			[ 'GESurfacingStructuredTasksEnabled' => false ],
			null,
			[],
			null
		];
		yield 'temp or anon user' => [
			[],
			null,
			[ 'isNamed' => false ],
			null
		];
		yield 'page not in main namespace' => [
			[],
			null,
			[ 'getNamespace' => NS_HELP ],
			null
		];
		yield 'editing the page' => [
			[],
			null,
			[ 'action' => 'edit' ],
			null
		];
		yield 'editing the page with VisualEditor' => [
			[],
			null,
			[ 'veaction' => 'edit' ],
			null
		];
		yield 'using Vector2022 skin' => [
			[],
			null,
			[],
			SkinVector22::class
		];
		yield 'using Vector skin' => [
			[],
			null,
			[],
			SkinVectorLegacy::class
		];
		yield 'user has edited' => [
			[],
			null,
			[ 'getEditCount' => 1 ],
			null
		];
		yield 'LinkRecommendations task being disabled in CommunityConfiguration' => [
			[],
			// no task types ⬇️
			[],
			[],
			null
		];
	}

	/**
	 * @dataProvider provideNoSurfacingTaskScenarios
	 */
	public function testDoesNotLoad(
		$configOverrides,
		$configLoaderOverrides,
		$outputPageOverrides,
		$skinOverride
	): void {
		$config = $this->getConfig( $configOverrides );
		$configurationLoader = $this->getConfigurationLoader( $configLoaderOverrides );
		$skin = $this->getStubSkin( $skinOverride );
		$mockOutputPage = $this->getMockOutputPage( $outputPageOverrides );

		$mockOutputPage
			->expects( $this->never() )
			->method( 'addJsConfigVars' );
		$mockOutputPage
			->expects( $this->never() )
			->method( 'addModules' );

		$hookHandler = new BeforePageDisplayHookHandler( $config, $configurationLoader );
		$hookHandler->onBeforePageDisplay( $mockOutputPage, $skin );
	}

	private function getConfig( array $overrides = [] ): HashConfig {
		return new HashConfig( [
			'GESurfacingStructuredTasksEnabled' => $overrides['GESurfacingStructuredTasksEnabled'] ?? true,
		] );
	}

	public function getConfigurationLoader( ?array $overrideTaskTypes = null ): ConfigurationLoader {
		if ( $overrideTaskTypes !== null ) {
			return new StaticConfigurationLoader( $overrideTaskTypes );
		}
		return new StaticConfigurationLoader( [
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => new LinkRecommendationTaskType(
				LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
				TaskType::DIFFICULTY_EASY
			)
		] );
	}

	/**
	 * @return OutputPage|(OutputPage&MockObject)
	 */
	public function getMockOutputPage( array $overrides = [] ): OutputPage {
		$mockOutputPage = $this->createMock( OutputPage::class );

		$stubUser = $this->createStub( 'User' );
		$stubUser->method( 'isNamed' )->willReturn( $overrides['isNamed'] ?? true );
		$stubUser->method( 'getEditCount' )->willReturn( $overrides['getEditCount'] ?? 0 );
		$mockOutputPage->method( 'getUser' )->willReturn( $stubUser );

		$stubWikipage = $this->createStub( 'Title' );
		$stubWikipage->method( 'getNamespace' )->willReturn( $overrides['getNamespace'] ?? NS_MAIN );
		$mockOutputPage->method( 'getTitle' )->willReturn( $stubWikipage );

		$stubRequest = $this->createStub( 'WebRequest' );
		$stubRequest->method( 'getVal' )->willReturnMap( [
			[ 'action', 'view', $overrides['action'] ?? 'view' ],
			[ 'veaction', null, $overrides['veaction'] ?? null ],
		] );
		$mockOutputPage->method( 'getRequest' )->willReturn( $stubRequest );
		return $mockOutputPage;
	}

	public function getStubSkin( ?string $skinOverride = null ): Skin {
		return $this->createMock( $skinOverride ?? SkinMinerva::class );
	}
}
