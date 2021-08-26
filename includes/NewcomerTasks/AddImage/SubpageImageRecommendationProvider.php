<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\Recommendation;
use GrowthExperiments\NewcomerTasks\SubpageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
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

	/** @inheritDoc */
	protected static $subpageName = 'addimage';

	/** @inheritDoc */
	protected static $serviceName = 'GrowthExperimentsImageRecommendationProvider';

	/** @inheritDoc */
	protected static $recommendationTaskTypeClass = ImageRecommendationTaskType::class;

	/**
	 * @inheritDoc
	 * @return ImageRecommendation
	 */
	public function createRecommendation( Title $title, array $data ): Recommendation {
		if ( isset( $data['pages'] ) ) {
			// This is the format used by the Image Suggestions API. It is not really useful
			// as a serialization format but easy to obtain for actual wiki pages so allow it
			// as a convenience.
			return ServiceImageRecommendationProvider::processApiResponseData( $title,
				$title->getPrefixedText(), $data );
		} else {
			return ImageRecommendation::fromArray( $data );
		}
	}

}
