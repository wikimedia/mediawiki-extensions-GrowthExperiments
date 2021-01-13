<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use RequestContext;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Provide stored recommendations for a given page.
 */
class AddLinkSuggestionsHandler extends SimpleHandler {

	use AddLinkHandlerTrait;

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
		$this->assertLinkRecommendationsEnabled( RequestContext::getMain() );
		$recommendation = $this->getLinkRecommendation( $title );
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
