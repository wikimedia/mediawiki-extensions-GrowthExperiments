<?php

namespace CirrusSearch;

use MediaWiki\Page\ProperPageIdentity;

class CirrusSearch {

	/**
	 * Request the setting of the weighted_tags field for the given tag(s) and weight(s).
	 * Will set a "$tagPrefix/$tagName" tag for each element of $tagNames, and will unset
	 * all other tags with the same prefix (in other words, this will replace the existing
	 * tag set for a given prefix). When $tagName is omitted, 'exists' will be used - this
	 * is canonical for tag types where the tag is fully determined by the prefix.
	 *
	 * This is meant for testing and non-production setups. For production a more efficient batched
	 * update process can be implemented outside MediaWiki.
	 *
	 * @param ProperPageIdentity $page
	 * @param string $tagPrefix
	 * @param string|string[]|null $tagNames
	 * @param int|int[]|null $tagWeights Tag weights (between 1-1000). When $tagNames is omitted,
	 *   $tagWeights should be a single number; otherwise it should be a tagname => weight map.
	 */
	public function updateWeightedTags(
		ProperPageIdentity $page,
		string $tagPrefix,
		$tagNames = null,
		$tagWeights = null
	): void {
	}

}
