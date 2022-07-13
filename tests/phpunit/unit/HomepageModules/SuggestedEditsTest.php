<?php

namespace GrowthExperiments\Tests\HomepageModules;

use ArrayIterator;
use GlobalVarConfig;
use GrowthExperiments\EditInfoService;
use GrowthExperiments\ExperimentUserManager;
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
use HashConfig;
use IContextSource;
use Language;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Extension\PageViewInfo\PageViewService;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiTestCaseTrait;
use MediaWikiUnitTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;
use OutputPage;
use TitleFactory;
use TitleValue;
use User;
use WANObjectCache;
use WebRequest;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \GrowthExperiments\HomepageModules\SuggestedEdits
 */
class SuggestedEditsTest extends MediaWikiUnitTestCase {

	use MediaWikiTestCaseTrait;

	protected function setUp(): void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	/**
	 * @covers ::getFiltersButtonGroupWidget
	 * @covers ::render
	 */
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

	private function getSuggestedEdits(): SuggestedEdits {
		$config = new HashConfig( [
			'GEHomepageSuggestedEditsEnabled' => true,
			'GEHomepageSuggestedEditsEnableTopics' => false
		] );
		$languageMock = $this->createMock( Language::class );
		$languageMock->method( 'getCode' )
			->willReturn( 'el' );
		$languageMock->method( 'getDir' )
			->willReturn( 'ltr' );
		$userMock = $this->createMock( User::class );
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getBoolOption' )
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
		$editInfoServiceMock = $this->createMock( EditInfoService::class );
		$experimentUserManagerMock = $this->createMock( ExperimentUserManager::class );
		$experimentUserManagerMock->method( 'getVariant' )
			->willReturn( 'X' );
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
		$databaseMock = $this->createMock( IDatabase::class );
		$databaseMock->expects( $this->once() )
			->method( 'select' )
			->willReturn( new ArrayIterator( [] ) );

		$protectionFilter = new ProtectionFilter(
			$titleFactoryMock,
			$linkBatchFactoryMock,
			$databaseMock
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
			$editInfoServiceMock,
			$experimentUserManagerMock,
			$pageViewServiceMock,
			$staticConfigLoader,
			$newcomerTasksUserOptionsLookupMock,
			$taskSuggester,
			$titleFactoryMock,
			$protectionFilter,
			$userOptionsLookupMock,
			$linkRecommendationFilter,
			$imageRecommendationFilter
		) extends SuggestedEdits {

			public function resetTaskCache(
				UserIdentity $user, TaskSetFilters $taskSetFilters, array $suggesterOptions
			) {
				// no-op to avoid triggering DeferredUpdates which are not allowed in unit tests.
			}
		};
	}
}
