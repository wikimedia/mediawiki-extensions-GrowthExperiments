<?php

namespace GrowthExperiments\NewcomerTasks\Topic;

class StaticTopicRegistry implements ITopicRegistry {
	/** @var Topic[]|null */
	private ?array $topics;

	/**
	 * @param Topic[] $topics
	 */
	public function __construct( array $topics = [] ) {
		$this->topics = $topics;
	}

	public function getTopics(): array {
		return $this->topics;
	}

	/** @inheritDoc */
	public function getTopicsMap(): array {
		$topics = $this->getTopics();
		return array_combine( array_map( static function ( Topic $topic ) {
			return $topic->getId();
		}, $topics ), $topics );
	}
}
