<?php

namespace GrowthExperiments\Tests\HomepageModules;

use GrowthExperiments\EditInfoService;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\StaticConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\Task\Task;
use GrowthExperiments\NewcomerTasks\TaskSuggester\StaticTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use Language;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Extensions\PageViewInfo\PageViewService;
use OOUI\BlankTheme;
use OOUI\Theme;
use OutputPage;
use WebRequest;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \GrowthExperiments\HomepageModules\SuggestedEdits
 */
class SuggestedEditsTest extends \MediaWikiUnitTestCase {

	use \MediaWikiTestCaseTrait;

	protected function setUp() : void {
		parent::setUp();
		Theme::setSingleton( new BlankTheme() );
	}

	/**
	 * @covers ::getFiltersButtonGroupWidget
	 * @covers ::render
	 */
	public function testNoTopicFiltersWhenTopicMatchingDisabled() {
		$suggestedEdits = $this->getSuggestedEdits();
		$out = $suggestedEdits->render( SuggestedEdits::RENDER_DESKTOP );
		$wrapper = TestingAccessWrapper::newFromObject( $suggestedEdits );
		$widget = $wrapper->buttonGroupWidget;
		$this->assertNotEmpty( $out );
		$widgetItems = $widget->getItems();
		$this->assertCount( 1, $widgetItems );
	}

	private function getSuggestedEdits() : SuggestedEdits {
		$config = new \HashConfig( [
			'GEHomepageSuggestedEditsEnabled' => true,
			'GEHomepageSuggestedEditsRequiresOptIn' => false,
			'GEHomepageSuggestedEditsEnableTopics' => false
		] );
		$outputMock = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->setMethodsExcept()
			->getMock();
		$languageMock = $this->getMockBuilder( Language::class )
			->disableOriginalConstructor()
			->getMock();
		$languageMock->method( 'getCode' )
			->willReturn( 'el' );
		$languageMock->method( 'getDir' )
			->willReturn( 'ltr' );
		$userMock = $this->getMockBuilder( \User::class )
			->disableOriginalConstructor()
			->getMock();
		$userMock->method( 'getBoolOption' )
			->with( SuggestedEdits::ACTIVATED_PREF )
			->willReturn( true );
		$requestMock = $this->getMockBuilder( WebRequest::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getCheck' ] )
			->getMock();
		$requestMock->method( 'getCheck' )
			->with( 'resetTaskCache' )
			->willReturn( false );

		$contextMock = $this->getMockBuilder( \IContextSource::class )
			->disableOriginalConstructor()
			->getMock();
		$contextMock->method( 'getConfig' )
			->willReturn( $config );
		$contextMock->method( 'getUser' )
			->willReturn( $userMock );
		$contextMock->method( 'getOutput' )
			->willReturn( $outputMock );
		$contextMock->method( 'getLanguage' )
			->willReturn( $languageMock );
		$contextMock->method( 'getRequest' )
			->willReturn( $requestMock );
		$contextMock->method( 'msg' )
			->willReturn( $this->getMockMessage() );
		$editInfoServiceMock = $this->getMockBuilder( EditInfoService::class )
			->disableOriginalConstructor()
			->getMock();
		$experimentUserManagerMock = $this->getMockBuilder( ExperimentUserManager::class )
			->disableOriginalConstructor()
			->getMock();
		$experimentUserManagerMock->method( 'getVariant' )
			->willReturn( 'X' );
		$pageViewServiceMock = $this->getMockBuilder( PageViewService::class )
			->disableOriginalConstructor()
			->getMock();
		$taskType = new TaskType( 'foo', TaskType::DIFFICULTY_HARD );
		$staticConfigLoader = new StaticConfigurationLoader( [ $taskType ] );
		$newcomerTasksUserOptionsLookupMock = $this->getMockBuilder(
			NewcomerTasksUserOptionsLookup::class
		)->disableOriginalConstructor()
			->getMock();
		$taskSuggester = new StaticTaskSuggester(
			[ new Task( $taskType, new \TitleValue( 0, 'foo' ) ) ]
		);
		$titleFactoryMock = $this->getMockBuilder( \TitleFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$linkBatchFactoryMock = $this->getMockBuilder( LinkBatchFactory::class )
			->disableOriginalConstructor()
			->getMock();

		$protectionFilter = new ProtectionFilter(
			$titleFactoryMock,
			$linkBatchFactoryMock
		);
		return new SuggestedEdits(
			$contextMock,
			$editInfoServiceMock,
			$experimentUserManagerMock,
			$pageViewServiceMock,
			$staticConfigLoader,
			$newcomerTasksUserOptionsLookupMock,
			$taskSuggester,
			$titleFactoryMock,
			$protectionFilter
		);
	}

}
