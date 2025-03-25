<?php

namespace GrowthExperiments\Tests\Unit;

use CirrusSearch\Search\SearchContext;
use GrowthExperiments\EventLogging\GrowthExperimentsInteractionLogger;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationStore;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use GrowthExperiments\NewcomerTasks\NewcomerTasksInfo;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\UnderlinkedFunctionScoreBuilder;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use JobQueueGroup;
use MediaWiki\Config\HashConfig;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use MessageLocalizer;
use Wikimedia\Stats\StatsFactory;

/**
 * @coversDefaultClass \GrowthExperiments\HomepageHooks
 */
class HomepageHooksTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->assertInstanceOf( HomepageHooks::class, $this->getHomepageHooksMock() );
	}

	/**
	 * @covers ::onCirrusSearchScoreBuilder
	 */
	public function testOnCirrusSearchScoreBuilder() {
		$taskTypeMock = $this->createMock( LinkRecommendationTaskType::class );
		$taskTypeMock->method( 'getUnderlinkedWeight' )->willReturn( 1.0 );
		$taskTypeMock->method( 'getUnderlinkedMinLength' )->willReturn( 2 );
		$taskTypes = [
			LinkRecommendationTaskTypeHandler::TASK_TYPE_ID => $taskTypeMock,
		];

		$configurationLoaderMock = $this->createMock( ConfigurationLoader::class );
		$configurationLoaderMock->method( 'getTaskTypes' )->willReturn( $taskTypes );
		$homepageHooks = $this->getHomepageHooksMock(
			null, null, null, null,
			$configurationLoaderMock
		);

		$searchContextMock = $this->createNoOpMock( SearchContext::class );
		$function = [ 'type' => UnderlinkedFunctionScoreBuilder::TYPE ];

		$builder = null;
		$retval = $homepageHooks->onCirrusSearchScoreBuilder( $function, $searchContextMock, $builder );

		$this->assertFalse( $retval );
		$this->assertInstanceOf( UnderlinkedFunctionScoreBuilder::class, $builder );
	}

	/**
	 * @covers ::onCirrusSearchScoreBuilder
	 */
	public function testOnCirrusSearchScoreBuilderDisabled() {
		$configurationLoaderMock = $this->createMock( ConfigurationLoader::class );
		$configurationLoaderMock->method( 'getTaskTypes' )->willReturn( [] );
		$homepageHooks = $this->getHomepageHooksMock(
			null, null, null, null,
			$configurationLoaderMock
		);

		$searchContextMock = $this->createNoOpMock( SearchContext::class );
		$function = [ 'type' => UnderlinkedFunctionScoreBuilder::TYPE ];

		$builder = null;
		$retval = $homepageHooks->onCirrusSearchScoreBuilder( $function, $searchContextMock, $builder );

		$this->assertFalse( $retval );
		$this->assertNotNull( $builder );
	}

	/**
	 * @covers ::onContributeCards
	 */
	public function testOnContributeCards() {
		$titleFactoryMock = $this->createMock( TitleFactory::class );
		$specialPageFactoryMock = $this->createMock( SpecialPageFactory::class );
		$specialPageFactoryMock->method( 'getLocalNameFor' )
			->willReturn( 'Homepage' );
		$homepageTitleMock = $this->createMock( Title::class );
		$homepageTitleMock->method( 'getLinkURL' )->willReturn( '/foo/bar/' );
		$titleFactoryMock->method( 'newFromLinkTarget' )
			->willReturn( $homepageTitleMock );
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userIdentity = new UserIdentityValue( 1, 'Foo' );
		$userOptionsLookupMock->method( 'getBoolOption' )
			->with( $userIdentity, HomepageHooks::HOMEPAGE_PREF_ENABLE )
			->willReturn( true );
		$homepageHooks = $this->getHomepageHooksMock(
			new HashConfig( [
				'GEHomepageEnabled' => true,
			] ),
			$titleFactoryMock,
			$specialPageFactoryMock,
			$userOptionsLookupMock
		);

		$homepageHooks->setUserIdentity( $userIdentity );

		$messageLocalizerMock = $this->createMock( MessageLocalizer::class );
		$messageMock = $this->createMock( Message::class );
		$messageMock->method( 'text' )->willReturn( 'Foo' );
		$messageLocalizerMock->method( 'msg' )->willReturn( $messageMock );
		$outputPageMock = $this->createMock( OutputPage::class );
		$homepageHooks->setMessageLocalizer( $messageLocalizerMock );
		$homepageHooks->setOutputPage( $outputPageMock );
		$cards = [];
		$homepageHooks->onContributeCards( $cards );
		$this->assertArrayEquals( [ [
			'title' => 'Foo',
			'icon' => 'lightbulb',
			'description' => 'Foo',
			'action' => [
				'action' => '/foo/bar/',
				'actionText' => 'Foo',
				'actionType' => 'link'
			] ]
		], $cards );

		// Scenario if Homepage is globally disabled, user has pref enabled
		$homepageHooks = $this->getHomepageHooksMock(
			new HashConfig( [ 'GEHomepageEnabled' => false ] ),
		);
		$homepageHooks->setUserIdentity( $userIdentity );
		$cards = [];
		$homepageHooks->onContributeCards( $cards );
		$this->assertArrayEquals( [], $cards );

		// Scenario if Homepage is globally enabled, user has pref disabled
		$userOptionsLookupMock = $this->createMock( UserOptionsLookup::class );
		$userOptionsLookupMock->method( 'getBoolOption' )
			->with( $userIdentity, HomepageHooks::HOMEPAGE_PREF_ENABLE )
			->willReturn( false );
		$homepageHooks = $this->getHomepageHooksMock(
			new HashConfig( [ 'GEHomepageEnabled' => true ] ),
			$titleFactoryMock,
			$specialPageFactoryMock,
			$userOptionsLookupMock
		);
		$homepageHooks->setUserIdentity( $userIdentity );
		$homepageHooks->setMessageLocalizer( $messageLocalizerMock );
		$homepageHooks->setOutputPage( $outputPageMock );
		$cards = [];
		$homepageHooks->onContributeCards( $cards );
		$this->assertArrayEquals( [], $cards );
	}

	private function getHomepageHooksMock(
		?HashConfig $config = null,
		?TitleFactory $titleFactoryMock = null,
		?SpecialPageFactory $specialPageFactoryMock = null,
		?UserOptionsLookup $userOptionsLookup = null,
		?ConfigurationLoader $configurationLoaderMock = null
	): HomepageHooks {
		return new HomepageHooks(
			$config ?? new HashConfig( [] ),
			$this->createNoOpMock( UserOptionsManager::class ),
			$userOptionsLookup ?? $this->createNoOpMock( UserOptionsLookup::class ),
			$this->createNoOpMock( UserIdentityUtils::class ),
			$this->createNoOpMock( NamespaceInfo::class ),
			$titleFactoryMock ?? $this->createNoOpMock( TitleFactory::class ),
			$this->createNoOpMock( StatsFactory::class ),
			$this->createNoOpMock( JobQueueGroup::class ),
			$configurationLoaderMock ?? $this->createNoOpMock( ConfigurationLoader::class ),
			$this->createNoOpMock( CampaignConfig::class ),
			$this->createNoOpMock( ExperimentUserManager::class ),
			$this->createNoOpMock( TaskTypeHandlerRegistry::class ),
			$this->createNoOpMock( TaskSuggesterFactory::class ),
			$this->createNoOpMock( NewcomerTasksUserOptionsLookup::class ),
			$this->createNoOpMock( LinkRecommendationStore::class ),
			$specialPageFactoryMock ?? $this->createNoOpMock( SpecialPageFactory::class ),
			$this->createNoOpMock( NewcomerTasksChangeTagsManager::class ),
			$this->createNoOpMock( NewcomerTasksInfo::class ),
			$this->createNoOpMock( UserImpactLookup::class ),
			$this->createNoOpMock( UserImpactStore::class ),
			$this->createNoOpMock( GrowthExperimentsInteractionLogger::class )
		);
	}
}
