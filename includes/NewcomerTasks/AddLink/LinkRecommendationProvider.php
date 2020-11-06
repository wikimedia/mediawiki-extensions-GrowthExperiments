<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

use MediaWiki\Linker\LinkTarget;
use StatusValue;

/**
 * Provides link recommendations for articles.
 */
interface LinkRecommendationProvider {

	/**
	 * Get a link recommendation (or an error message) for a given article.
	 * A warning status is used when the title had no recommendations, and a fatal status when
	 * there was some unexpected error.
	 * @param LinkTarget $title
	 * @return LinkRecommendation|StatusValue
	 */
	public function get( LinkTarget $title );

}
