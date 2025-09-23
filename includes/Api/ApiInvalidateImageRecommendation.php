<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggesterFactory;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerAwareTrait;
use Wikimedia\Assert\Assert;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Endpoint for invalidating image recommendation.
 *
 * This is used when the recommendation is determined to be invalid upon display (for example,
 * when the article already has an image). See mw.libs.ge.AddImageArticleTarget.
 */
class ApiInvalidateImageRecommendation extends ApiBase {
	use LoggerAwareTrait;

	private AddImageSubmissionHandler $imageSubmissionHandler;
	private TaskSuggesterFactory $taskSuggesterFactory;
	private NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup;
	private TitleFactory $titleFactory;
	private ConfigurationLoader $configurationLoader;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		ConfigurationLoader $configurationLoader,
		AddImageSubmissionHandler $imageSubmissionHandler,
		TaskSuggesterFactory $taskSuggesterFactory,
		NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
		TitleFactory $titleFactory
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->configurationLoader = $configurationLoader;
		$this->imageSubmissionHandler = $imageSubmissionHandler;
		$this->taskSuggesterFactory = $taskSuggesterFactory;
		$this->newcomerTasksUserOptionsLookup = $newcomerTasksUserOptionsLookup;
		$this->titleFactory = $titleFactory;

		$this->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
	}

	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		// This API is used by external clients for their own structured task workflows so
		// include disabled task types.
		$allTaskTypes = $this->configurationLoader->getTaskTypes()
			+ $this->configurationLoader->getDisabledTaskTypes();
		$taskType = $allTaskTypes[$params['tasktype']] ?? null;
		if ( $taskType === null ) {
			$this->logger->warning(
				'Task type {tasktype} was not found in configuration',
				[
					'tasktype' => $params['tasktype'],
				]
			);
			$this->dieWithError(
				[ 'growthexperiments-homepage-imagesuggestiondata-not-in-config', $params['tasktype'] ],
				'not-in-config'
			);
		}

		Assert::parameterType( ImageRecommendationBaseTaskType::class, $taskType, '$taskType' );
		'@phan-var ImageRecommendationBaseTaskType $taskType';
		/** @var ImageRecommendationBaseTaskType $taskType */
		$titleValue = $params['title'];

		$page = $this->titleFactory->newFromLinkTarget( $titleValue )->toPageIdentity();
		if ( $page->exists() ) {
			$this->imageSubmissionHandler->invalidateRecommendation(
				$taskType,
				$page,
				$this->getAuthority()->getUser()->getId(),
				null,
				$params['filename'],
				$params['sectiontitle'],
				$params['sectionnumber'],
			);
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'status' => 'ok',
			] );
		} else {
			$this->dieWithError( [ 'apierror-invalidtitle', $titleValue->getDBkey() ] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'tasktype' => [
				ParamValidator::PARAM_TYPE => [
					// Do not filter out non-existing task-types: during API structure tests
					// none of the task types exist and an empty list would cause test failures.
					ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
					SectionImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
				],
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => ImageRecommendationTaskTypeHandler::TASK_TYPE_ID,
			],
			'title' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'title',
				TitleDef::PARAM_RETURN_OBJECT => true,
			],
			'filename' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'sectiontitle' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'sectionnumber' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'integer',
			],
		];
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}
}
