<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\ErrorException;
use GrowthExperiments\FeatureManager;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationHelper;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Provide stored recommendations for a given page.
 */
class AddLinkSuggestionsHandler extends SimpleHandler {

	private LinkRecommendationHelper $linkRecommendationHelper;
	private FeatureManager $featureManager;

	public function __construct(
		LinkRecommendationHelper $linkRecommendationHelper,
		FeatureManager $featureManager
	) {
		$this->linkRecommendationHelper = $linkRecommendationHelper;
		$this->featureManager = $featureManager;
	}

	/**
	 * Entry point.
	 * @param LinkTarget $title
	 * @return Response|mixed A Response or a scalar passed to ResponseFactory::createFromReturnValue
	 * @throws HttpException
	 */
	public function run( LinkTarget $title ) {
		if (
			!$this->featureManager->areLinkRecommendationsEnabled() ||
			!$this->featureManager->isNewcomerTasksAvailable()
		) {
			throw new HttpException( 'Disabled', 404 );
		}
		try {
			$recommendation = $this->linkRecommendationHelper->getLinkRecommendation( $title );
		} catch ( ErrorException $e ) {
			throw new HttpException( $e->getErrorMessageInEnglish() );
		}
		if ( !$recommendation ) {
			throw new HttpException( 'The recommendation has already been invalidated', 409 );
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
