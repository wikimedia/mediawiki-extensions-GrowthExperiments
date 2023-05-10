<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use ApiResult;
use Config;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use IApiMessage;
use MediaWiki\Title\TitleFactory;
use StatusValue;
use Title;
use Wikimedia\Assert\Assert;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Query module to support fetching image metadata from the Image Suggestion API.
 *
 * - Users must be logged-in
 * - Rate limits apply
 * - Image suggestion metadata is cached
 */
class ApiQueryImageSuggestionData extends ApiQueryBase {

	private ImageRecommendationProvider $imageRecommendationProvider;
	private ConfigurationLoader $configurationLoader;
	private Config $config;
	private TitleFactory $titleFactory;

	/**
	 * @param ApiQuery $mainModule
	 * @param string $moduleName
	 * @param ImageRecommendationProvider $imageRecommendationProvider
	 * @param ConfigurationLoader $configurationLoader
	 * @param Config $config
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		ApiQuery $mainModule,
		$moduleName,
		ImageRecommendationProvider $imageRecommendationProvider,
		ConfigurationLoader $configurationLoader,
		Config $config,
		TitleFactory $titleFactory
	) {
		parent::__construct( $mainModule, $moduleName, 'gisd' );
		$this->imageRecommendationProvider = $imageRecommendationProvider;
		$this->configurationLoader = $configurationLoader;
		$this->config = $config;
		$this->titleFactory = $titleFactory;
	}

	/** @inheritDoc */
	public function execute() {
		$user = $this->getUser();
		if ( $user->isAnon() ) {
			$this->dieWithError( [ 'apierror-mustbeloggedin-generic' ] );
		}

		if ( $user->pingLimiter( 'growthexperiments-apiqueryimagesuggestiondata' ) ) {
			$this->dieWithError( 'apierror-ratelimited' );
		}
		$params = $this->extractRequestParams();
		$enabledTaskTypes = $this->configurationLoader->getTaskTypes();
		$taskType = $enabledTaskTypes[$params['tasktype']] ?? null;
		Assert::parameterType( ImageRecommendationBaseTaskType::class, $taskType, '$taskType' );
		'@phan-var ImageRecommendationBaseTaskType $taskType';

		$continueTitle = null;
		if ( $params['continue'] !== null ) {
			$continue = $this->parseContinueParamOrDie( $params['continue'], [ 'int', 'string' ] );
			$continueTitle = $this->titleFactory->makeTitleSafe( $continue[0], $continue[1] );
			$this->dieContinueUsageIf( !$continueTitle );
		}
		$pageSet = $this->getPageSet();
		// Allow non-existing pages in developer setup, to facilitate local development/testing.
		$pages = $this->config->get( 'GEDeveloperSetup' )
			? $pageSet->getGoodAndMissingPages()
			: $pageSet->getGoodPages();
		foreach ( $pages as $pageApiId => $pageIdentity ) {
			if ( $continueTitle && !$continueTitle->equals( $pageIdentity ) ) {
				continue;
			}
			$title = Title::castFromPageIdentity( $pageIdentity );
			if ( !$title ) {
				continue;
			}
			$metadata = $this->imageRecommendationProvider->get(
				$title,
				$taskType
			);
			$fit = null;
			if ( $metadata instanceof ImageRecommendation ) {
				$fit = $this->addPageSubItem(
					$pageApiId,
					$metadata->toArray()
				);
			} elseif ( !$this->hasErrorCode( $metadata, 'growthexperiments-no-recommendation-found' ) ) {
				// like ApiQueryBase::addPageSubItems but we want to use a different path
				$errorArray = $this->getErrorFormatter()->arrayFromStatus( $metadata );
				$path = [ 'query', 'pages', $pageApiId ];
				ApiResult::setIndexedTagName( $errorArray, 'growthimagesuggestiondataerrors' );
				$fit = $this->getResult()->addValue( $path, 'growthimagesuggestiondataerrors', $errorArray );
			}
			if ( $fit === false ) {
				$this->setContinueEnumParameter(
					'continue',
					$title->getNamespace() . '|' . $title->getText()
				);
				break;
			}
		}
	}

	/** @inheritDoc */
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
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	private function hasErrorCode( StatusValue $status, string $errorCode ): bool {
		foreach ( $status->getErrors() as $error ) {
			$message = $error['message'];
			if ( $message instanceof IApiMessage && $message->getApiCode() === $errorCode ) {
				return true;
			}
		}
		return false;
	}

}
