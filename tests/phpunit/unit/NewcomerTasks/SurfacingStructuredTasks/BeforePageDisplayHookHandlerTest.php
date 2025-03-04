<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\EventLogging\GrowthExperimentsInteractionLogger;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\SurfacingStructuredTasks\BeforePageDisplayHookHandler;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\VariantHooks;
use MediaWiki\Config\HashConfig;
use MediaWiki\Minerva\Skins\SkinMinerva;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
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

		$hookHandler = new BeforePageDisplayHookHandler(
			$config,
			$configurationLoader,
			$this->getUserOptionsLookupMock(),
			$this->getStubLinkRecommendationStore( $this->createMock( LinkRecommendation::class ) ),
			$this->createMock( GrowthExperimentsInteractionLogger::class )
		);
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
		yield 'protected page' => [
			[],
			null,
			[ 'canEdit' => false ],
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
		yield 'No recommendations for page' => [
			[],
			null,
			[],
			null,
			false
		];
	}

	/**
	 * @dataProvider provideNoSurfacingTaskScenarios
	 */
	public function testDoesNotLoad(
		$configOverrides,
		$configLoaderOverrides,
		$outputPageOverrides,
		$skinOverride,
		$pageHasRecommendations = true
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

		$hookHandler = new BeforePageDisplayHookHandler(
			$config,
			$configurationLoader,
			$this->getUserOptionsLookupMock(),
			$this->getStubLinkRecommendationStore(
				$pageHasRecommendations ? $this->createMock( LinkRecommendation::class ) : null
			),
			$this->createMock( GrowthExperimentsInteractionLogger::class )
		);
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

	private function getStubLinkRecommendationStore( $returnValue ) {
		$linkRecommendationStore = $this->createMock( LinkRecommendationStore::class );

		$linkRecommendationStore
			->method( 'getByPageId' )
			->willReturn( $returnValue );
		return $linkRecommendationStore;
	}

	/**
	 * @return OutputPage|(OutputPage&MockObject)
	 */
	public function getMockOutputPage( array $overrides = [] ): OutputPage {
		$mockOutputPage = $this->createMock( OutputPage::class );

		$stubUser = $this->createStub( User::class );
		$stubUser->method( 'isNamed' )->willReturn( $overrides['isNamed'] ?? true );
		$stubUser->method( 'getEditCount' )->willReturn( $overrides['getEditCount'] ?? 0 );
		$stubUser->method( 'probablyCan' )->willReturn( $overrides['canEdit'] ?? true );
		$mockOutputPage->method( 'getUser' )->willReturn( $stubUser );

		$stubWikipage = $this->createStub( Title::class );
		$stubWikipage->method( 'getNamespace' )->willReturn( $overrides['getNamespace'] ?? NS_MAIN );
		$stubWikipage->method( 'getArticleID' )->willReturn( 123 );
		$mockOutputPage->method( 'getTitle' )->willReturn( $stubWikipage );

		$stubRequest = $this->createStub( WebRequest::class );
		$stubRequest->method( 'getVal' )->willReturnMap( [
			[ 'action', 'view', $overrides['action'] ?? 'view' ],
			[ 'veaction', null, $overrides['veaction'] ?? null ],
		] );
		$mockOutputPage->method( 'getRequest' )->willReturn( $stubRequest );
		return $mockOutputPage;
	}

	private function getStubSkin( ?string $skinOverride = null ): Skin {
		return $this->createMock( $skinOverride ?? SkinMinerva::class );
	}

	/**
	 * @return UserOptionsLookup|mixed|MockObject
	 */
	private function getUserOptionsLookupMock() {
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getOption' )
			->with( $this->anything(), VariantHooks::USER_PREFERENCE )
			->willReturn( VariantHooks::VARIANT_SURFACING_STRUCTURED_TASK );
		return $userOptionsLookupMock;
	}
}
