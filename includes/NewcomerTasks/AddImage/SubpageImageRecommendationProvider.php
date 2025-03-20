<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use GrowthExperiments\NewcomerTasks\SubpageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationBaseTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\SectionImageRecommendationTaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use StatusValue;
use Wikimedia\Assert\Assert;

/**
 * Enable by adding the following to LocalSettings.php or a similar location:
 *     $class = \GrowthExperiments\NewcomerTasks\AddImage\SubpageLinkRecommendationProvider::class;
 *     $wgHooks['MediaWikiServices'][] = "$class::onMediaWikiServices";
 *     $wgHooks['ContentHandlerDefaultModelFor'][] = "$class::onContentHandlerDefaultModelFor";
 *
 * @inheritDoc
 */
class SubpageImageRecommendationProvider
	extends SubpageRecommendationProvider
	implements ImageRecommendationProvider
{

	private ImageRecommendationMetadataProvider $metadataProvider;

	private ImageRecommendationApiHandler $apiHandler;

	public function __construct(
		WikiPageFactory $wikiPageFactory,
		RecommendationProvider $fallbackRecommendationProvider,
		ImageRecommendationMetadataProvider $metadataProvider,
		ImageRecommendationApiHandler $imageRecommendationApiHandler
	) {
		parent::__construct( $wikiPageFactory, $fallbackRecommendationProvider );
		$this->metadataProvider = $metadataProvider;
		$this->apiHandler = $imageRecommendationApiHandler;
	}

	/** @inheritDoc */
	protected static $subpageName = 'addimage';

	/** @inheritDoc */
	protected static $serviceName = 'GrowthExperimentsImageRecommendationProvider';

	/** @inheritDoc */
	protected static $recommendationTaskTypeClass = [
		ImageRecommendationTaskType::class,
		SectionImageRecommendationTaskType::class,
	];

	/**
	 * @inheritDoc
	 * @return ImageRecommendation|StatusValue
	 */
	public function createRecommendation(
		Title $title,
		TaskType $taskType,
		array $data,
		array $suggestionFilters = []
	) {
		Assert::parameterType( ImageRecommendationBaseTaskType::class, $taskType, '$taskType' );
		'@phan-var ImageRecommendationBaseTaskType $taskType';
		/** @var ImageRecommendationBaseTaskType $taskType */

		if ( isset( $data['pages'] ) || isset( $data['rows'] ) || isset( $data['query']['pages'] ) ) {
			// This is the format used by the Image Suggestions API. It is not really useful
			// as a serialization format but easy to obtain for actual wiki pages so allow it
			// as a convenience.
			return ServiceImageRecommendationProvider::processApiResponseData(
				$taskType,
				$title,
				$title->getPrefixedText(),
				$this->apiHandler->getSuggestionDataFromApiResponse( $data, $taskType ),
				$this->metadataProvider,
				null
			);
		} else {
			return ImageRecommendation::fromArray( $data );
		}
	}

	/** @inheritDoc */
	public static function onMediaWikiServices( MediaWikiServices $services ): void {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$services->addServiceManipulator( static::$serviceName,
			static function (
				RecommendationProvider $recommendationProvider,
				MediaWikiServices $services
			) use ( $growthServices ): ImageRecommendationProvider {
				$subpageProvider = new static(
					$services->getWikiPageFactory(),
					$recommendationProvider,
					new StaticImageRecommendationMetadataProvider(
						$growthServices->getImageRecommendationMetadataService(),
						$services->getContentLanguageCode()->toString(),
						$services->getContentLanguage()->getFallbackLanguages(),
						$services->getLanguageNameUtils(),
						new DerivativeContext( RequestContext::getMain() ),
						$services->getSiteStore()
					),
					$growthServices->getImageRecommendationApiHandler()
				);
				return new CacheBackedImageRecommendationProvider(
					$services->getMainWANObjectCache(),
					$subpageProvider,
					$services->getStatsFactory()
				);
			}
		);
	}

}
