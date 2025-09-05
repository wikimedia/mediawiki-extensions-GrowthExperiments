<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use LogicException;
use MediaWiki\Message\Message;
use MessageLocalizer;
use Wikimedia\JsonCodec\JsonCodecable;
use Wikimedia\JsonCodec\JsonCodecableTrait;

/**
 * A topic represents a subgroup of tasks based on the topic of the associated page
 * (such as biology-related tasks or tasks related to Japan).
 * Topic objects should also contain all the configuration necessary for filtering
 * to that topic in TaskSuggester.
 */
class Topic implements JsonCodecable {

	use JsonCodecableTrait;

	/** @var string */
	protected $id;

	/** @var string */
	private $groupId;

	/**
	 * @param string $id Topic ID, a string consisting of lowercase alphanumeric characters
	 *   and dashes. E.g. 'biology'.
	 * @param string|null $groupId Topic group, for visual grouping. E.g. 'science'.
	 */
	public function __construct( string $id, ?string $groupId = null ) {
		$this->id = $id;
		$this->groupId = $groupId;
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
	 * Human-readable name of the topic.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getName( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-topic-name-'
			. $this->getId() );
	}

	/**
	 * Topic group ID. Topics in the same group are related; can be used e.g. for visual
	 * grouping of topics.
	 * @return string|null
	 */
	public function getGroupId() {
		return $this->groupId;
	}

	/**
	 * Human-readable name of the topic group. Must not be called when getGroupId() is null.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getGroupName( MessageLocalizer $messageLocalizer ): Message {
		if ( $this->groupId === null ) {
			throw new LogicException( 'getGroupName should not be called when getGroupId is null' );
		}
		return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-topic-group-name-'
			. $this->getGroupId() );
	}

	/**
	 * Return an array (JSON-ish) representation of the topic.
	 * @param MessageLocalizer $messageLocalizer
	 * @return array
	 */
	public function getViewData( MessageLocalizer $messageLocalizer ) {
		return [
			'id' => $this->getId(),
			'name' => $this->getName( $messageLocalizer )->text(),
			'groupId' => $this->getGroupId(),
			'groupName' => $this->getGroupId() ? $this->getGroupName( $messageLocalizer )->text() : null,
		];
	}

	/** @inheritDoc */
	public function toJsonArray(): array {
		return [
			'id' => $this->getId(),
			'groupId' => $this->getGroupId(),
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): self {
		return new static( $json['id'], $json['groupId'] );
	}

}
