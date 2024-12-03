<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use MediaWiki\Json\JsonDeserializer;
use MediaWiki\Language\RawMessage;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Message\Message;
use MediaWiki\Title\TitleValue;
use MessageLocalizer;

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

	/** @inheritDoc */
	protected function toJsonArray(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->name,
			'referencePages' => array_map( static function ( LinkTarget $page ) {
				return [ $page->getNamespace(), $page->getDBkey() ];
			}, $this->getReferencePages() ),
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonDeserializer $deserializer, array $json ) {
		$referencePages = array_map( static function ( array $page ) {
			return new TitleValue( $page[0], $page[1] );
		}, $json['referencePages'] );
		$topic = new MorelikeBasedTopic( $json['id'], $referencePages );
		$topic->setName( $json['name'] );
		return $topic;
	}

}
