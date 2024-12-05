<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

use MediaWiki\Json\JsonDeserializer;

class OresBasedTopic extends Topic {

	/** @var string[] */
	private $oresTopics;

	/**
	 * @param string $id Topic ID, a string consisting of lowercase alphanumeric characters
	 *   and dashes. E.g. 'biology'.
	 * @param string|null $groupId Topic group, for visual grouping. E.g. 'science'.
	 * @param string[] $oresTopics ORES topic IDs which define this topic.
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
