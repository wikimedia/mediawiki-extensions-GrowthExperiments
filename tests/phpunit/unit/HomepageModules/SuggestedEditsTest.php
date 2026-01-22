<?php

namespace GrowthExperiments\Tests\Unit;

use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\ExperimentXLabManager;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\LinkRecommendationFilter;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeManager;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PageViewInfo\PageViewService;
use MediaWiki\Language\Language;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWikiTestCaseTrait;
use MediaWikiUnitTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \GrowthExperiments\HomepageModules\SuggestedEdits
 */
class SuggestedEditsTest extends MediaWikiUnitTestCase {

	use MediaWikiTestCaseTrait;

	protected function setUp(): void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	public function testNoTopicFiltersWhenTopicMatchingDisabled() {
		if ( !interface_exists( PageViewService::class ) ) {
			$this->markTestSkipped( 'PageViewService not installed' );
		}
		$suggestedEdits = $this->getSuggestedEdits();
		$out = $suggestedEdits->render( SuggestedEdits::RENDER_DESKTOP );
		$wrapper = TestingAccessWrapper::newFromObject( $suggestedEdits );
		$widget = $wrapper->buttonGroupWidget;
		$this->assertNotEmpty( $out );
		$widgetItems = $widget->getItems();
		$this->assertCount( 1, $widgetItems );
	}

	public function testAddsReviseToneIfNotInitiated(): void {
		$taskTypeManager = $this->createMock( TaskTypeManager::class );
		$taskTypeManager->method( 'getTaskTypesForUser' )->willReturn( [ 'copyedit' ] );
		$user = $this->createMock( User::class );
		$userOptionsManagerMock = $this->createMock( UserOptionsManager::class );
		$userOptionsManagerMock->method( 'getOption' )->willReturnCallback( static function ( $user, $option ) {
			return match ( $option ) {
				'growthexperiments-revise-tone-treatment-initiated' => null,
				default => null,
			};
		}
		);
		$userOptionsManagerMock->expects( $this->exactly( 2 ) )->method( 'setOption' )->withConsecutive(
			[
				$user,
				'growthexperiments-revise-tone-treatment-initiated',
				true,
			],
			[
				$user,
				'growthexperiments-homepage-se-filters',
				'["copyedit","revise-tone"]',
			],
		);

		$experimentUserManagerMock =
			$this->createMock( ExperimentUserManager::class );
		$experimentUserManagerMock->method( 'getVariant' )
			->willReturn( ExperimentXLabManager::REVISE_TONE_EXPERIMENT_TREATMENT_GROUP_NAME );
		$experimentUserManagerMock->method( 'isUserInVariant' )->willReturn( true );
		$suggestedEdits = $this->getSuggestedEdits( [
			'user' => $user,
			'userOptionsManager' => $userOptionsManagerMock,
			'experimentUserManager' => $experimentUserManagerMock,
			'taskTypeManager' => $taskTypeManager,
		] );

		$suggestedEdits->render( SuggestedEdits::RENDER_DESKTOP );
	}

	public function testAddsNoReviseToneIfInitiated(): void {
		$taskTypeManager = $this->createMock( TaskTypeManager::class );
		$taskTypeManager->method( 'getTaskTypesForUser' )->willReturn( [ 'copyedit' ] );
		$user = $this->createMock( User::class );
		$userOptionsManagerMock = $this->createMock( UserOptionsManager::class );
		$userOptionsManagerMock->method( 'getOption' )->willReturnCallback( static function ( $user, $option ) {
			return match ( $option ) {
				'growthexperiments-revise-tone-treatment-initiated' => true,
				default => null,
			};
		}
		);
		$userOptionsManagerMock->expects( $this->never() )->method( 'setOption' );

		$experimentUserManagerMock =
			$this->createMock( ExperimentUserManager::class );
		$experimentUserManagerMock->method( 'getVariant' )
			->willReturn( ExperimentXLabManager::REVISE_TONE_EXPERIMENT_TREATMENT_GROUP_NAME );
		$experimentUserManagerMock->method( 'isUserInVariant' )->willReturn( true );
		$suggestedEdits = $this->getSuggestedEdits( [
			'user' => $user,
			'userOptionsManager' => $userOptionsManagerMock,
			'experimentUserManager' => $experimentUserManagerMock,
			'taskTypeManager' => $taskTypeManager,
		] );

		$suggestedEdits->render( SuggestedEdits::RENDER_DESKTOP );
	}

	private function getSuggestedEdits( array $overrides = [] ): SuggestedEdits {
		$config = new HashConfig( [
			'GEHomepageSuggestedEditsEnabled' => true,
			'GEHomepageSuggestedEditsEnableTopics' => false,
		] );
		$languageMock = $this->createMock( Language::class );
		$languageMock->method( 'getCode' )
			->willReturn( 'el' );
		$languageMock->method( 'getDir' )
			->willReturn( 'ltr' );
		$userMock = $overrides[ 'user' ] ?? $this->createMock( User::class );
		if ( isset( $overrides[ 'userOptionsManager' ] ) ) {
			$userOptionsManagerMock = $overrides[ 'userOptionsManager' ];
		} else {
			$userOptionsManagerMock = $this->createMock( UserOptionsManager::class );
		}
		$userOptionsManagerMock->method( 'getBoolOption' )
			->with( $userMock, SuggestedEdits::ACTIVATED_PREF )
			->willReturn( true );
		$requestMock = $this->createNoOpMock( WebRequest::class, [ 'getCheck' ] );
		$requestMock->method( 'getCheck' )
			->with( 'resetTaskCache' )
			->willReturn( false );

		$contextMock = $this->createMock( IContextSource::class );
		$contextMock->method( 'getConfig' )
			->willReturn( $config );
		$contextMock->method( 'getUser' )
			->willReturn( $userMock );
		$contextMock->method( 'getOutput' )
			->willReturn( $this->createMock( OutputPage::class ) );
		$contextMock->method( 'getLanguage' )
			->willReturn( $languageMock );
		$contextMock->method( 'getRequest' )
			->willReturn( $requestMock );
		$contextMock->method( 'msg' )
			->willReturn( $this->getMockMessage() );

		if ( isset( $overrides[ 'experimentUserManager' ] ) ) {
			$experimentUserManagerMock = $overrides[ 'experimentUserManager' ];
		} else {
			$experimentUserManagerMock =
				$this->createMock( ExperimentUserManager::class );
			$experimentUserManagerMock->method( 'getVariant' )
				->willReturn( 'X' );
		}

		$pageViewServiceMock = $this->createMock( PageViewService::class );
		$taskType = new TaskType( 'foo', TaskType::DIFFICULTY_HARD );
		$staticConfigLoader = new StaticConfigurationLoader( [ $taskType ] );
		$newcomerTasksUserOptionsLookupMock = $this->createMock( NewcomerTasksUserOptionsLookup::class );
		$newcomerTasksUserOptionsLookupMock->method( 'getTopics' )
			->willReturn( [] );
		$newcomerTasksUserOptionsLookupMock->method( 'getTopicsMatchMode' )
			->willReturn( SearchStrategy::TOPIC_MATCH_MODE_OR );

		$taskSuggester = new StaticTaskSuggester(
			[ new Task( $taskType, new TitleValue( 0, 'foo' ) ) ]
		);
		$titleFactoryMock = $this->createMock( TitleFactory::class );
		$linkBatchFactoryMock = $this->createMock( LinkBatchFactory::class );
		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder->method( $this->logicalOr( 'select', 'from', 'where', 'caller' ) )->willReturnSelf();
		$queryBuilder->method( 'fetchResultSet' )->willReturn( new FakeResultWrapper( [] ) );
		$databaseMock = $this->createMock( IReadableDatabase::class );
		$databaseMock->method( 'newSelectQueryBuilder' )->willReturn( $queryBuilder );
		$connProvider = $this->createMock( IConnectionProvider::class );
		$connProvider->method( 'getReplicaDatabase' )->willReturn( $databaseMock );

		$protectionFilter = new ProtectionFilter(
			$titleFactoryMock,
			$linkBatchFactoryMock,
			$connProvider
		);
		$linkRecommendationFilter = new LinkRecommendationFilter(
			$this->createMock( LinkRecommendationStore::class )
		);
		$imageRecommendationFilter = new ImageRecommendationFilter(
			$this->createMock( WANObjectCache::class )
		);
		$campaignConfig = new CampaignConfig( [] );
		return new class(
			$contextMock,
			GlobalVarConfig::newInstance(),
			$campaignConfig,
			$experimentUserManagerMock,
			$pageViewServiceMock,
			$staticConfigLoader,
			$newcomerTasksUserOptionsLookupMock,
			$taskSuggester,
			$titleFactoryMock,
			$protectionFilter,
			$userOptionsManagerMock,
			$linkRecommendationFilter,
			$imageRecommendationFilter,
			StatsFactory::newNull(),
			$this->createMock( ITopicRegistry::class ),
			$overrides[ 'taskTypeManager' ] ?? $this->createMock( TaskTypeManager::class )
		) extends SuggestedEdits {

			public function resetTaskCache(
				UserIdentity $user, TaskSetFilters $taskSetFilters, array $suggesterOptions
			) {
				// no-op to avoid triggering DeferredUpdates which are not allowed in unit tests.
			}
		};
	}
}
