<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\GrowthExperimentsServices;
use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use GrowthExperiments\NewcomerTasks\SubpageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use StatusValue;
use Title;

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

	/** @var ImageRecommendationMetadataProvider */
	private $metadataProvider;

	/** @var AddImageSubmissionHandler */
	private $imageSubmissionHandler;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param RecommendationProvider $fallbackRecommendationProvider
	 * @param ImageRecommendationMetadataProvider $metadataProvider
	 * @param AddImageSubmissionHandler $imageSubmissionHandler
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory,
		RecommendationProvider $fallbackRecommendationProvider,
		ImageRecommendationMetadataProvider $metadataProvider,
		AddImageSubmissionHandler $imageSubmissionHandler
	) {
		parent::__construct( $wikiPageFactory, $fallbackRecommendationProvider );
		$this->metadataProvider = $metadataProvider;
		$this->imageSubmissionHandler = $imageSubmissionHandler;
	}

	/** @inheritDoc */
	protected static $subpageName = 'addimage';

	/** @inheritDoc */
	protected static $serviceName = 'GrowthExperimentsImageRecommendationProvider';

	/** @inheritDoc */
	protected static $recommendationTaskTypeClass = ImageRecommendationTaskType::class;

	/**
	 * @inheritDoc
	 * @return ImageRecommendation|StatusValue
	 */
	public function createRecommendation( Title $title, array $data, array $suggestionFilters = [] ) {
		if ( isset( $data['pages'] ) ) {
			// This is the format used by the Image Suggestions API. It is not really useful
			// as a serialization format but easy to obtain for actual wiki pages so allow it
			// as a convenience.
			return ServiceImageRecommendationProvider::processApiResponseData(
				$title,
				$title->getPrefixedText(),
				$data,
				$this->metadataProvider,
				$this->imageSubmissionHandler,
				$suggestionFilters
			);
		} else {
			return ImageRecommendation::fromArray( $data );
		}
	}

	/** @inheritDoc */
	public static function onMediaWikiServices( MediaWikiServices $services ) {
		$growthServices = GrowthExperimentsServices::wrap( $services );
		$services->addServiceManipulator( static::$serviceName,
			static function (
				RecommendationProvider $recommendationProvider,
				MediaWikiServices $services
			) use ( $growthServices ) {
				return new static(
					$services->getWikiPageFactory(),
					$recommendationProvider,
					$growthServices->getImageRecommendationMetadataProvider(),
					$growthServices->getAddImageSubmissionHandler()
				);
			}
		);
	}

}
