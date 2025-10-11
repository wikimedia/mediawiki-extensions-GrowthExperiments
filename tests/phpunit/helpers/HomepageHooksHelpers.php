<?php

namespace GrowthExperiments\Tests\Helpers;

use GrowthExperiments\EventLogging\GrowthExperimentsInteractionLogger;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageHooks;
use GrowthExperiments\LevelingUp\LevelingUpManager;
use GrowthExperiments\NewcomerTasks\CampaignConfig;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeManager;
use GrowthExperiments\UserImpact\UserImpactLookup;
use GrowthExperiments\UserImpact\UserImpactStore;
use MediaWiki\Config\HashConfig;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentityUtils;
use Wikimedia\Stats\StatsFactory;

trait HomepageHooksHelpers {

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
			$specialPageFactoryMock ?? $this->createNoOpMock( SpecialPageFactory::class ),
			$this->createNoOpMock( NewcomerTasksChangeTagsManager::class ),
			$this->createNoOpMock( UserImpactLookup::class ),
			$this->createNoOpMock( UserImpactStore::class ),
			$this->createNoOpMock( GrowthExperimentsInteractionLogger::class ),
			$this->createNoOpMock( TaskTypeManager::class ),
			$this->createNoOpMock( LevelingUpManager::class )
		);
	}

}
