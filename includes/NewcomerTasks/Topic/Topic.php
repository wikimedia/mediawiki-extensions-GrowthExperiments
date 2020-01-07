<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use Message;
use MessageLocalizer;

/**
 * A topic represents a subgroup of tasks based on the topic of the associated page
 * (such as biology-related tasks or tasks related to Japan).
 * Topic objects should also contain all the configuration necessary for filtering
 * to that topic in TaskSuggester.
 */
class Topic {

	/** @var string */
	protected $id;

	/** @var string FIXME temporary hack for topic names */
	protected $name;

	/**
	 * @param string $id Topic ID, a string consisting of lowercase alphanumeric characters
	 *   and dashes. E.g. 'biology'.
	 */
	public function __construct( $id ) {
		$this->id = $id;
		// FIXME while the raw name hack is in effect, if setName() is somehow not called,
		//   this is better than the RawMessage constructor throwing an error
		$this->name = $id;
	}

	/**
	 * Returns the topic ID, a string consisting of lowercase alphanumeric characters
	 * and dashes (e.g. 'biology').
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * FIXME temporary hack for non-localizable topic names
	 * @param string $name Topic name as raw text
	 */
	public function setName( $name ) {
		$this->name = $name;
	}

	/**
	 * Human-readable name of the topic.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getName( MessageLocalizer $messageLocalizer ): Message {
		// FIXME we don't localize for now because the list of topics is soon to be revamped
		//   and we want to avoid wasting translator time
		// return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-topic-name-'
		//	. $this->getId() );
		// FIXME MessageLocalizer does not work with raw messages. The language does not matter
		//   for RawMessage, but we have to set something to avoid triggering session loading.
		return ( new \RawMessage( $this->name ) )->inLanguage( 'en' );
	}

	/**
	 * Return an array (JSON-ish) representation of the topic.
	 * @param MessageLocalizer $messageLocalizer
	 * @return array
	 */
	public function toArray( MessageLocalizer $messageLocalizer ) {
		return [
			'id' => $this->getId(),
			'name' => $this->getName( $messageLocalizer )->text(),
		];
	}

}
