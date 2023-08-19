<?php

namespace GrowthExperiments\NewcomerTasks\AddImage;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Title\Title;
use MWHttpRequest;
use StatusValue;

/**
 * Interface for interfacing with the image recommendation API, allowing different API endpoints
 * and formats to be used
 */
interface ImageRecommendationApiHandler {
	/**
	 * Get the API request object used to retrieve image recommendations
	 *
	 * @param Title $title
	 * @param TaskType $taskType
	 * @return MWHttpRequest|StatusValue
	 */
	public function getApiRequest( Title $title, TaskType $taskType );

	/**
	 * Get an array of suggestion data from response returned by the API
	 *
	 * @param array $apiResponse
	 * @param TaskType $taskType
	 * @return ImageRecommendationData[]|StatusValue
	 */
	public function getSuggestionDataFromApiResponse( array $apiResponse, TaskType $taskType );
}
