<?php
declare( strict_types = 1 );

namespace GrowthExperiments\Api;

use GrowthExperiments\FeatureManager;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\ImageRecommendationFilter;
use GrowthExperiments\NewcomerTasks\InterestValidator;
use GrowthExperiments\NewcomerTasks\LinkRecommendationFilter;
use GrowthExperiments\NewcomerTasks\ProtectionFilter;
use GrowthExperiments\NewcomerTasks\Task\TaskSet;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFilters;
use GrowthExperiments\NewcomerTasks\Task\TaskSetFiltersFactory;
use GrowthExperiments\NewcomerTasks\TaskSuggester\NewcomerTasksCacheRefreshJob;
use GrowthExperiments\NewcomerTasks\TaskSuggester\SearchStrategy\SearchStrategy;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\ITopicRegistry;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiPageSet;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryGeneratorBase;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use StatusValue;
use Wikimedia\Message\ListType;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * API endpoint for Newcomer Tasks feature.
 * @see https://www.mediawiki.org/wiki/Growth/Personalized_first_day/Newcomer_tasks
 */
class ApiQueryGrowthTasks extends ApiQueryGeneratorBase {

	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		private readonly JobQueueGroup $jobQueueGroup,
		private readonly TaskSuggesterFactory $taskSuggesterFactory,
		private readonly ConfigurationLoader $configurationLoader,
		private readonly LinkRecommendationFilter $linkRecommendationFilter,
		private readonly ImageRecommendationFilter $imageRecommendationFilter,
		private readonly ProtectionFilter $protectionFilter,
		private readonly ITopicRegistry $topicRegistry,
		private readonly FeatureManager $featureManager,
		private readonly TaskSetFiltersFactory $taskSetFiltersFactory,
		private readonly InterestValidator $interestValidator,
	) {
		parent::__construct( $queryModule, $moduleName, 'gt' );
	}

	/** @inheritDoc */
	public function execute() {
		$this->run();
	}

	/** @inheritDoc */
	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	protected function run( ?ApiPageSet $resultPageSet = null ): void {
		$user = $this->getUser();
		if ( !$user->isNamed() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic' );
		}
		$params = $this->extractRequestParams();
		$taskTypes = $params['tasktypes'];
		$topics = $params['topics'];
		$topicsMode = $params['topicsmode'];
		$limit = $params['limit'];
		$offset = $params['offset'];
		$debug = $params['debug'];
		$excludePageIds = $params['excludepageids'] ?? [];
		// The topics parameter defaults to an empty array, which counts as set.
		$this->requireMaxOneParameter(
			[ 'topics' => $topics ?: null, 'interests' => $params['interests'] ],
			'topics', 'interests'
		);
		if ( $params['interests'] !== null ) {
			$interests = $this->getUsableInterests( $params['interests'] );
		} else {
			// Explicit topics win over stored interests; TaskSetFilters allows only one
			// of the two.
			$interests = $topics ? [] : $this->taskSetFiltersFactory->getInterestFilters( $user );
		}
		// The cache holds one task set per user. Skip it when the front end loads more tasks
		// (exclude page IDs) and when it previews an unsaved interest selection.
		$useCache = !$excludePageIds && $params['interests'] === null;

		$taskSuggester = $this->taskSuggesterFactory->create();
		$taskSetFilters = new TaskSetFilters( $taskTypes, $topics, $topicsMode, $interests );

		/** @var TaskSet $tasks */
		$tasks = $taskSuggester->suggest(
			$user,
			$taskSetFilters,
			$limit,
			$offset,
			[
				'debug' => $debug,
				'excludePageIds' => $excludePageIds,
				'useCache' => $useCache,
			]
		);
		if ( $tasks instanceof StatusValue ) {
			$this->dieStatus( $tasks );
		}

		$tasks = $this->linkRecommendationFilter->filter( $tasks );
		$tasks = $this->imageRecommendationFilter->filter( $tasks );
		$tasks = $this->protectionFilter->filter( $tasks );

		$result = $this->getResult();
		$basePath = [ 'query', $this->getModuleName() ];
		$titles = [];
		$fits = true;
		$i = 0;
		// TODO: Consider grouping the data by "type" so on the client-side one could
		// access result.data.copyedit rather an iterating over everything.
		'@phan-var TaskSet $tasks';
		foreach ( $tasks as $i => $task ) {
			$title = Title::newFromLinkTarget( $task->getTitle() );
			$extraData = [
				'tasktype' => $task->getTaskType()->getId(),
				'difficulty' => $task->getTaskType()->getDifficulty(),
				'order' => $i,
				'qualityGateIds' => $task->getTaskType()->getQualityGateIds(),
				'qualityGateConfig' => $tasks->getQualityGateConfig(),
				'token' => $task->getToken(),
			];

			if ( $resultPageSet ) {
				$titles[] = $title;
				$resultPageSet->setGeneratorData( $title, $extraData );
			} else {
				$fits = $result->addValue( array_merge( $basePath, [ 'suggestions' ] ), null, [
					'title' => $title->getPrefixedText(),
				] + $extraData );
				if ( !$fits ) {
					// Could not add to ApiResult due to hitting response size limits.
					break;
				}
			}
		}
		// If we aborted because of $fits, $i is the 0-based index (relative to $offset) of which
		// item we need to continue with in the next request, so we need to start with $offset + $i.
		// If we finished (reached $limit) then $i points to the last task we successfully added.
		if ( !$fits || $tasks->getTotalCount() > $offset + $i + 1 ) {
			// $i is 0-based and will point to the first record not added, so the offset must be one larger.
			$this->setContinueEnumParameter( 'offset', $offset + $i + (int)$fits );
		}

		if ( $resultPageSet ) {
			$resultPageSet->populateFromTitles( $titles );
			$result->addValue( $this->getModuleName(), 'totalCount', $tasks->getTotalCount() );
			$result->addValue( $this->getModuleName(), 'qualityGateConfig', $tasks->getQualityGateConfig() );
			if ( $debug ) {
				$result->addValue( $this->getModuleName(), 'debug', $tasks->getDebugData() );
			}
		} else {
			$result->addValue( $basePath, 'totalCount', $tasks->getTotalCount() );
			$result->addValue( $basePath, 'qualityGateConfig', $tasks->getQualityGateConfig() );
			$result->addIndexedTagName( array_merge( $basePath, [ 'suggestions' ] ), 'suggestion' );
			if ( $debug ) {
				$result->addValue( $basePath, 'debug', $tasks->getDebugData() );
			}
		}
		if ( $useCache ) {
			// Refresh the cached suggestions via the job queue when the user hasn't asked to exclude
			// page IDs. This makes the API endpoint behave in the same way as SuggestedEdits.php on
			// Special:Homepage. If we don't do this, then repeat queries to this API endpoint with the same user
			// ID and without `pageids` set will result in returning the same cached task set.
			if ( !$user->isNamed() ) {
				\MediaWiki\Logger\LoggerFactory::getInstance( 'GrowthExperiments' )->error(
					'Scheduling NewcomerTasksCacheRefreshJob for non-named user',
					[
						'userId' => $user->getId(),
						'exception' => new \RuntimeException( 'T419172' ),
					]
				);
			}
			$this->jobQueueGroup->lazyPush(
				new JobSpecification( NewcomerTasksCacheRefreshJob::JOB_NAME, [
					'userId' => $user->getId(),
				] )
			);
		}
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		$taskTypes = $this->getTaskTypes();
		$topics = $this->getTopics();
		// Ensure valid values, tasks/topics might be empty during tests.
		$taskLimit = max( count( $taskTypes ), 1 );
		$topicsLimit = max( count( $topics ), 1 );

		return [
			'tasktypes' => [
				ParamValidator::PARAM_TYPE => array_keys( $taskTypes ),
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => $taskLimit,
				ParamValidator::PARAM_ISMULTI_LIMIT2 => $taskLimit,
				ParamValidator::PARAM_DEFAULT => [],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => array_map( function ( TaskType $taskType ) {
					return $taskType->getName( $this->getContext() );
				}, $taskTypes ),
			],
			'topics' => [
				ParamValidator::PARAM_TYPE => array_keys( $topics ),
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => $topicsLimit,
				ParamValidator::PARAM_ISMULTI_LIMIT2 => $topicsLimit,
				ParamValidator::PARAM_DEFAULT => [],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => array_map( function ( Topic $topic ) {
					return $topic->getName( $this->getContext() );
				}, $topics ),
			],
			'topicsmode' => [
				ParamValidator::PARAM_TYPE => SearchStrategy::TOPIC_MATCH_MODES,
			],
			'interests' => [
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => TaskSetFiltersFactory::MAX_INTERESTS,
				ParamValidator::PARAM_ISMULTI_LIMIT2 => TaskSetFiltersFactory::MAX_INTERESTS,
			],
			'limit' => [
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MAX => 250,
				IntegerDef::PARAM_MAX2 => 250,
			],
			'offset' => [
				ParamValidator::PARAM_TYPE => 'integer',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_IGNORE_RANGE => false,
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'debug' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
			'excludepageids' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_ISMULTI_LIMIT1 => 1000,
				ParamValidator::PARAM_ISMULTI_LIMIT2 => 1000,
			],
		];
	}

	/**
	 * Remove the interests that cannot get suggestions, and warn about them so that the
	 * caller can tell the user why the selection gives fewer suggestions than expected.
	 * Fail when no value of the selection can get suggestions.
	 * @param string[] $interests Prefixed titles, already validated by the title parameter type.
	 * @return string[] The interests that can get suggestions.
	 */
	private function getUsableInterests( array $interests ): array {
		[ 'valid' => $usable, 'invalid' => $unusable ] = $this->interestValidator->validate( $interests );
		if ( $unusable ) {
			$unusableList = Message::listParam( $unusable, ListType::COMMA );
			if ( $usable ) {
				$this->addWarning( [
					'apiwarn-growthexperiments-unusable-interests',
					$unusableList,
					count( $unusable ),
				] );
			} else {
				// The caller asked for suggestions about these interests. The unfiltered
				// suggestions are no answer to that request.
				$this->dieWithError( [
					'apierror-growthexperiments-no-usable-interests',
					$unusableList,
					count( $unusable ),
				] );
			}
		}
		return $usable;
	}

	/**
	 * @return Topic[] Array of topic id => topic
	 */
	protected function getTopics(): array {
		$topics = $this->topicRegistry->getTopics();
		return array_combine( array_map( static function ( Topic $topic ) {
			return $topic->getId();
		}, $topics ), $topics ) ?: [];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		$p = $this->getModulePrefix();
		return [
			"action=query&list=growthtasks&{$p}tasktypes=copyedit" => 'apihelp-query+growthtasks-example-1',
			"action=query&generator=growthtasks&g{$p}limit=max&prop=info|revision"
				=> 'apihelp-query+growthtasks-example-2',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:GrowthExperiments#API';
	}

	/**
	 * @return TaskType[]
	 */
	private function getTaskTypes(): array {
		// Prevent calls to suggested edits config when feature is disabled, (T369312)
		if ( !$this->featureManager->isNewcomerTasksAvailable() ) {
			return [];
		}
		return $this->configurationLoader->getTaskTypes();
	}

}
