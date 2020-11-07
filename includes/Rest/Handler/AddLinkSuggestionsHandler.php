<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Status;
use StatusValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Provide stored recommendations for a given page.
 */
class AddLinkSuggestionsHandler extends SimpleHandler {

	/** @var LinkRecommendationProvider */
	private $linkRecommendationProvider;

	/**
	 * @param LinkRecommendationProvider $linkRecommendationProvider
	 */
	public function __construct(
		LinkRecommendationProvider $linkRecommendationProvider
	) {
		$this->linkRecommendationProvider = $linkRecommendationProvider;
	}

	/**
	 * Entry point.
	 * @param LinkTarget $title
	 * @return Response|mixed A Response or a scalar passed to ResponseFactory::createFromReturnValue
	 */
	public function run( LinkTarget $title ) {
		$recommendation = $this->linkRecommendationProvider->get( $title );
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

}
