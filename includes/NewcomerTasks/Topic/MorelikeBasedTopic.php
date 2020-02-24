<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use MediaWiki\Linker\LinkTarget;
use Message;
use MessageLocalizer;
use RawMessage;

/**
 * A topic based on morelike search (text similarity with a predefined set of reference articles).
 */
class MorelikeBasedTopic extends Topic {

	/** @var LinkTarget[] */
	private $referencePages;

	/** @var string */
	private $name;

	/**
	 * @param string $id Topic ID, e.g. 'biology'.
	 * @param LinkTarget[] $referencePages The set of reference pages defining this topic.
	 */
	public function __construct( $id, array $referencePages ) {
		parent::__construct( $id );
		$this->referencePages = $referencePages;
		// If setName() is somehow not called, this is better than the RawMessage constructor
		// throwing an error.
		$this->name = $id;
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

	/** @inheritDoc */
	public function getName( MessageLocalizer $messageLocalizer ): Message {
		// HACK we don't localize morelike-based topics because it will be replaced by ORES topics
		//   in production, the list of ORES topics is different, and we want to avoid wasting
		//   translator time.
		// FIXME MessageLocalizer does not work with raw messages. The language does not matter
		//   for RawMessage, but we have to set something to avoid triggering session loading.
		return ( new RawMessage( $this->name ) )->inLanguage( 'en' );
	}

	/**
	 * Hack for non-localizable topic names.
	 * @param string $name Topic name as raw text
	 */
	public function setName( $name ) {
		$this->name = $name;
	}

}
