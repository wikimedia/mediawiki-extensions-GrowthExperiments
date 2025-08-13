<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskTypeHandler;
use GrowthExperiments\Util;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Api\ApiResult;
use MediaWiki\Api\IApiMessage;
use MediaWiki\Config\Config;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerAwareTrait;
use StatusValue;
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
	use LoggerAwareTrait;

	private ImageRecommendationProvider $imageRecommendationProvider;
	private ConfigurationLoader $configurationLoader;
	private Config $config;
	private TitleFactory $titleFactory;

	public function __construct(
		ApiQuery $mainModule,
		string $moduleName,
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

		$this->setLogger( LoggerFactory::getInstance( 'GrowthExperiments' ) );
	}

	/** @inheritDoc */
	public function execute() {
		$user = $this->getUser();
		if ( !$user->isNamed() ) {
			$this->dieWithError( [ 'apierror-mustbeloggedin-generic' ] );
		}

		if ( $user->pingLimiter( 'growthexperiments-apiqueryimagesuggestiondata' ) ) {
			$this->dieWithError( 'apierror-ratelimited' );
		}

		if ( !Util::isNewcomerTasksAvailable() ) {
			$this->dieWithError( [ 'apierror-moduledisabled', 'Suggested edits' ] );
		}

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
