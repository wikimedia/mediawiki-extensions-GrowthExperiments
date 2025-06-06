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
use MediaWiki\Page\WikiPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \GrowthExperiments\NewcomerTasks\SurfacingStructuredTasks\BeforePageDisplayHookHandler
 */
class BeforePageDisplayHookHandlerTest extends MediaWikiUnitTestCase {

	public static function provideSurfacingTaskScenarios(): iterable {
		yield 'User has not edited' => [];
		yield 'User has edited less than MAX_USER_EDITS' => [
			[
				'getEditCount' => BeforePageDisplayHookHandler::MAX_USER_EDITS - 1
			]
		];
	}

	/**
	 * @dataProvider provideSurfacingTaskScenarios
	 */
	public function testLoadsModuleAndAddsConfigData(
		array $outputPageOverrides = []
	): void {
		$config = $this->getConfig();
		$configurationLoader = $this->getConfigurationLoader();
		$skin = $this->getStubSkin();
		$mockOutputPage = $this->getMockOutputPage( $outputPageOverrides );

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
			$this->getStubLinkRecommendationStore(),
			$this->createMock( GrowthExperimentsInteractionLogger::class )
		);
		$hookHandler->onBeforePageDisplay( $mockOutputPage, $skin );
	}

	public static function provideNoSurfacingTaskScenarios(): iterable {
		yield 'Surfacing Structured Tasks disabled in config' => [
			[ 'GESurfacingStructuredTasksEnabled' => false ],
			null,
			[],
			null,
			[],
		];
		yield 'temp or anon user' => [
			[],
			null,
			[ 'isNamed' => false ],
			null,
			[],
		];
		yield 'protected page' => [
			[],
			null,
			[ 'canEdit' => false ],
			null,
			[],
		];
		yield 'page not in main namespace' => [
			[],
			null,
			[ 'getNamespace' => NS_HELP ],
			null,
			[],
		];
		yield 'editing the page' => [
			[],
			null,
			[ 'action' => 'edit' ],
			null,
			[],
		];
		yield 'viewing a specific revision of the page' => [
			[],
			null,
			[ 'oldid' => '12345' ],
			null,
			[],
		];
		yield 'viewing a diff of the page' => [
			[],
			null,
			[ 'diff' => 'next' ],
			null,
			[],
		];
		yield 'editing the page with VisualEditor' => [
			[],
			null,
			[ 'veaction' => 'edit' ],
			null,
			[],
		];
		yield 'user has reached the max of edits' => [
			[],
			null,
			[ 'getEditCount' => BeforePageDisplayHookHandler::MAX_USER_EDITS ],
			null,
			[],
		];
		yield 'LinkRecommendations task being disabled in CommunityConfiguration' => [
			[],
			// no task types ⬇️
			[],
			[],
			null,
			[],
		];
		yield 'No recommendations for page' => [
			[],
			null,
			[],
			null,
			[ 'getByPageId' => null ],
		];

		$taskTypes = [
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => new LinkRecommendationTaskType(
				LinkRecommendationTaskTypeHandler::TASK_TYPE_ID,
				TaskType::DIFFICULTY_EASY,
				[],
				[],
				[],
				[ 'ExcludedCategory' ],
			)
		];
		yield 'Page has excluded category' => [
			[],
			$taskTypes,
			[ 'pageCategories' => [ 'ExcludedCategory' ] ],
			null,
			[],
		];
		yield 'Page has excluded template' => [
			[],
			null,
			[],
			null,
			[ 'getNumberOfExcludedTemplatesOnPage' => 1 ],
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
		array $linkRecommendationStoreOverrides,
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
				$linkRecommendationStoreOverrides
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

	private function getStubLinkRecommendationStore( array $overrides = [] ): LinkRecommendationStore {
		$linkRecommendationStore = $this->createMock( LinkRecommendationStore::class );

		$linkRecommendationStore
			->method( 'getByPageId' )
			->willReturn(
				array_key_exists( 'getByPageId', $overrides ) ?
					$overrides['getByPageId'] :
					$this->createMock( LinkRecommendation::class )
		);
		$linkRecommendationStore
			->method( 'getNumberOfExcludedTemplatesOnPage' )
			->willReturn(
				$overrides['getNumberOfExcludedTemplatesOnPage'] ?? 0
		);

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

		$stubTitle = $this->createStub( Title::class );
		$stubTitle->method( 'getNamespace' )->willReturn( $overrides['getNamespace'] ?? NS_MAIN );
		$stubTitle->method( 'getArticleID' )->willReturn( 123 );
		$mockOutputPage->method( 'getTitle' )->willReturn( $stubTitle );

		$stubWikipage = $this->createStub( WikiPage::class );
		if ( isset( $overrides['pageCategories'] ) ) {
			$getCategoriesReturnValue = [];
			foreach ( $overrides['pageCategories'] as $categoryTitle ) {
				$stubCategory = $this->createStub( Title::class );
				$stubCategory->method( 'getDBkey' )->willReturn( $categoryTitle );
				$getCategoriesReturnValue[] = $stubCategory;
			}
			$stubWikipage->method( 'getCategories' )->willReturn( $getCategoriesReturnValue );
		} else {
			$stubWikipage->method( 'getCategories' )->willReturn( [] );
		}
		$mockOutputPage->method( 'getWikiPage' )->willReturn( $stubWikipage );

		$stubRequest = $this->createStub( WebRequest::class );
		$stubRequest->method( 'getVal' )->willReturnMap( [
			[ 'action', 'view', $overrides['action'] ?? 'view' ],
			[ 'veaction', null, $overrides['veaction'] ?? null ],
			[ 'oldid', null, $overrides['oldid'] ?? null ],
			[ 'diff', null, $overrides['diff'] ?? null ],
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
