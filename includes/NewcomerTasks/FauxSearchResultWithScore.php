<?php

namespace GrowthExperiments\NewcomerTasks;

use FauxSearchResult;
use MediaWiki\Title\Title;

/**
 * A manually constructed search result, for use with FauxSearchResultSet.
 * Unlike FauxSearchResult in core, it has a concept of CirrusSearch-like match scores.
 * FIXME Since core does not have a concept of scores, and the SearchResult hierarchy is a mess,
 *   there is no nice way to express that this is a potential CirrusSearchResult replacement;
 *   code using it must be aware of it, or do duck typing.
 */
class FauxSearchResultWithScore extends FauxSearchResult {

	/** @var float Match score */
	protected $score;

	/**
	 * @param Title $title
	 * @param float $score
	 */
	public function __construct( Title $title, float $score = 0 ) {
		parent::__construct( $title );
		$this->score = $score;
	}

	/**
	 * Return match score for this result.
	 * @return float
	 */
	public function getScore() {
		return $this->score;
	}

}
