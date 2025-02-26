<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use LogicException;
use MediaWiki\Extension\WikimediaMessages\ArticleTopicFiltersRegistry;
use MediaWiki\Json\JsonDeserializer;
use MediaWiki\Message\Message;
use MessageLocalizer;

class OresBasedTopic extends Topic {

	/** @var string[] */
	private $oresTopics;

	/**
	 * @param string $id Topic ID, a string consisting of lowercase alphanumeric characters
	 *   and dashes. E.g. 'biology'.
	 * @param string|null $groupId Topic group, for visual grouping. E.g. 'science'.
	 * @param string[] $oresTopics ORES topic IDs which define this topic.
	 * @note Callers are responsible for making sure that the provided topic and group IDs are valid.
	 */
	public function __construct( string $id, ?string $groupId, array $oresTopics ) {
		parent::__construct( $id, $groupId );
		$this->oresTopics = $oresTopics;
	}

	/**
	 * ORES topic IDs which define this topic.
	 * @return string[]
	 */
	public function getOresTopics(): array {
		return $this->oresTopics;
	}

	/** @inheritDoc */
	public function getName( MessageLocalizer $messageLocalizer ): Message {
		$topicID = $this->getId();
		$msgKey = ArticleTopicFiltersRegistry::getTopicMessages( [ $topicID ] )[$topicID];
		return $messageLocalizer->msg( $msgKey );
	}

	/** @inheritDoc */
	public function getGroupName( MessageLocalizer $messageLocalizer ): Message {
		$groupID = $this->getGroupId();
		if ( $groupID === null ) {
			throw new LogicException( 'getGroupName should not be called when getGroupId is null' );
		}
		$allTopics = ArticleTopicFiltersRegistry::getGroupedTopics();
		foreach ( $allTopics as $group ) {
			if ( $group['groupId'] === $groupID ) {
				return $messageLocalizer->msg( $group['msgKey'] );
			}
		}
		throw new LogicException( "Unrecognized topic group ID '$groupID'" );
	}

	/** @inheritDoc */
	protected function toJsonArray(): array {
		return [
			'id' => $this->getId(),
			'groupId' => $this->getGroupId(),
			'oresTopics' => $this->getOresTopics(),
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonDeserializer $deserializer, array $json ) {
		return new OresBasedTopic( $json['id'], $json['groupId'], $json['oresTopics'] );
	}

}
