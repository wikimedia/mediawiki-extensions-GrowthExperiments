<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendation;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\LinkRecommendationTaskTypeHandler;
use GrowthExperiments\Util;
use GrowthExperiments\WikiConfigException;
use IContextSource;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Rest\HttpException;
use Status;
use StatusValue;

trait AddLinkHandlerTrait {

	/** @var ConfigurationLoader */
	private $configurationLoader;

	/** @var LinkRecommendationProvider */
	private $linkRecommendationProvider;

	/**
	 * @param IContextSource $contextSource
	 * @throws HttpException
	 */
	protected function assertLinkRecommendationsEnabled( IContextSource $contextSource ) {
		if ( !Util::areLinkRecommendationsEnabled( $contextSource ) ) {
			throw new HttpException( 'Disabled', 404 );
		}
	}

	/**
	 * Return the link recommendation stored for the given title.
	 * @param LinkTarget $title
	 * @return LinkRecommendation
	 * @throws HttpException
	 */
	protected function getLinkRecommendation( LinkTarget $title ): LinkRecommendation {
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
			throw new HttpException( $error, 404 );
		}
		return $recommendation;
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
