<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Status;
use StatusValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Provide stored recommendations for a given page.
 */
class AddLinkSuggestionsHandler extends SimpleHandler {

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var LinkRecommendationProvider */
	private $linkRecommendationProvider;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param LinkRecommendationProvider $linkRecommendationProvider
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader,
		LinkRecommendationProvider $linkRecommendationProvider
	) {
		$this->configurationLoader = $configurationLoader;
		$this->linkRecommendationProvider = $linkRecommendationProvider;
	}

	/**
	 * Entry point.
	 * @param LinkTarget $title
	 * @return Response|mixed A Response or a scalar passed to ResponseFactory::createFromReturnValue
	 * @throws HttpException
	 */
	public function run( LinkTarget $title ) {
		$taskTypeId = LinkRecommendationTaskTypeHandler::TASK_TYPE_ID;
		$taskType = $this->configurationLoader->getTaskTypes()[$taskTypeId] ?? null;
		if ( !$taskType ) {
			throw $this->configError( new WikiConfigException( "No such task type: $taskTypeId" ) );
		} elseif ( !$taskType instanceof LinkRecommendationTaskType ) {
			throw $this->configError( new WikiConfigException(
				"Not a link recommendation task type: $taskTypeId" ) );
		}
		$recommendation = $this->linkRecommendationProvider->get( $title, $taskType );
		if ( $recommendation instanceof StatusValue ) {
			$error = Status::wrap( $recommendation )->getWikiText();
			return $this->getResponseFactory()->createHttpError( 404, [ 'error' => $error ] );
		}
		return [ 'recommendation' => $recommendation->toArray() ];
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return false;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'title' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_REQUIRED => true,
				TitleDef::PARAM_RETURN_OBJECT => true,
			],
		];
	}

	/**
	 * @param WikiConfigException $error
	 * @return HttpException
	 */
	private function configError( WikiConfigException $error ): HttpException {
		Util::logError( $error );
		return new HttpException( $error->getMessage() );
	}

}
