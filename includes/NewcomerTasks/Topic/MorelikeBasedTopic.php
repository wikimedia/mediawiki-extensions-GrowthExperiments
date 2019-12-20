<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use MediaWiki\Linker\LinkTarget;

/**
 * A topic based on morelike search (text similarity with a predefined set of reference articles).
 */
class MorelikeBasedTopic extends Topic {

	/** @var LinkTarget[] */
	private $referencePages;

	/**
	 * @param string $id Topic ID, e.g. 'biology'.
	 * @param LinkTarget[] $referencePages The set of reference pages defining this topic.
	 */
	public function __construct( $id, array $referencePages ) {
		parent::__construct( $id );
		$this->referencePages = $referencePages;
	}

	/**
	 * Return the set of reference pages defining this topic. These are "representative" articles
	 * about the topic, and articles will be classified into the given topic based on a text
	 * similarity metric.
	 * @return LinkTarget[]
	 */
	public function getReferencePages(): array {
		return $this->referencePages;
	}

}
