<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiQuery;
use ApiQueryBase;
use Config;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendation;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskTypeHandler;
use Title;
use TitleFactory;

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
		$enabledTaskTypes = $this->configurationLoader->getTaskTypes();
		$imageRecommendationTaskType = $enabledTaskTypes[ImageRecommendationTaskTypeHandler::TASK_TYPE_ID] ?? null;
		if ( !$imageRecommendationTaskType instanceof ImageRecommendationTaskType ) {
			// We could improve on this message with something more specific to this
			// scenario, but probably not worth it for the additional work required
			// of translators
			$this->dieWithError( [
				'growthexperiments-newcomertasks-invalid-tasktype', ImageRecommendationTaskTypeHandler::TASK_TYPE_ID
			] );
		}
		$continueTitle = null;
		$params = $this->extractRequestParams();
		if ( $params['continue'] !== null ) {
			$continue = $this->parseContinueParamOrDie( $params['continue'], [ 'int', 'string' ] );
			$continueTitle = $this->titleFactory->makeTitleSafe( $continue[0], $continue[1] );
			$this->dieContinueUsageIf( !$continueTitle );
		}
		$pageSet = $this->getPageSet();
		// Allow non-existing pages in developer setup, to facilitate local development/testing.
		$pages = $this->config->get( 'GEDeveloperSetup' ) ? $pageSet->getPages() : $pageSet->getGoodPages();
		foreach ( $pages as $pageIdentity ) {
			if ( $continueTitle && !$continueTitle->equals( $pageIdentity ) ) {
				continue;
			}
			$title = Title::castFromPageIdentity( $pageIdentity );
			if ( !$title ) {
				continue;
			}
			$metadata = $this->imageRecommendationProvider->get(
				$title,
				$imageRecommendationTaskType
			);
			if ( $metadata instanceof ImageRecommendation ) {
				$fit = $this->addPageSubItem(
					$pageIdentity->getId(), $metadata->toArray(), 'growthimagesuggestiondata'
				);
				if ( !$fit ) {
					$this->setContinueEnumParameter(
						'continue',
						$title->getNamespace() . '|' . $title->getText()
					);
					break;
				}
			}
		}
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			]
		];
	}

}
