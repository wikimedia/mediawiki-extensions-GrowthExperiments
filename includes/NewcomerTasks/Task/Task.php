<?php

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Json\JsonUnserializable;
use MediaWiki\Json\JsonUnserializableTrait;
use MediaWiki\Json\JsonUnserializer;
use MediaWiki\Linker\LinkTarget;
use TitleValue;

/**
 * A single task recommendation.
 * A Task specifies a page and the type of the task to perform on it.
 */
class Task implements JsonUnserializable {

	use JsonUnserializableTrait;

	/** @var TaskType */
	private $taskType;

	/** @var LinkTarget The page to edit. */
	private $title;

	/** @var Topic[] */
	private $topics = [];

	/** @var float[] Match scores associated to the topics in $topics, keyed by topic ID. */
	private $topicScores = [];

	/**
	 * @param TaskType $taskType
	 * @param LinkTarget $title The page this task is about.
	 */
	public function __construct( TaskType $taskType, LinkTarget $title ) {
		$this->taskType = $taskType;
		$this->title = $title;
	}

	/**
	 * @return TaskType
	 */
	public function getTaskType(): TaskType {
		return $this->taskType;
	}

	/**
	 * @return LinkTarget
	 */
	public function getTitle(): LinkTarget {
		return $this->title;
	}

	/**
	 * Topics of the underlying article. Depending on the how topics are implemented, this
	 * might be never set, even if the TaskSuggester otherwise supports topic search.
	 * @return Topic[]
	 */
	public function getTopics(): array {
		return $this->topics;
	}

	/**
	 * Get topic matching scores for each topic this task is in.
	 * @return float[] Topic ID => score
	 */
	public function getTopicScores(): array {
		// Make sure the set of returned items always matches getTopics().
		$topicScores = [];
		foreach ( $this->getTopics() as $topic ) {
			$topicScores[$topic->getId()] = $this->topicScores[$topic->getId()] ?? 0;
		}
		return $topicScores;
	}

	/**
	 * @param Topic[] $topics
	 * @param float[] $topicScores Match scores associated to the topics in $topics,
	 *   keyed by topic ID. Keys are a subset of those in $topics.
	 */
	public function setTopics( array $topics, array $topicScores = [] ): void {
		$this->topics = $topics;
		$this->topicScores = $topicScores;
	}

	/** @inheritDoc */
	protected function toJsonArray(): array {
		return [
			'taskType' => $this->getTaskType()->jsonSerialize(),
			'title' => [ $this->getTitle()->getNamespace(), $this->getTitle()->getDBkey() ],
			'topics' => array_map( function ( Topic $topic ) {
				return $topic->jsonSerialize();
			}, $this->getTopics() ),
			'topicScores' => $this->getTopicScores(),
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonUnserializer $unserializer, array $json ) {
		$taskType = $unserializer->unserialize( $json['taskType'], TaskType::class );
		$title = new TitleValue( $json['title'][0], $json['title'][1] );
		$topics = array_map( function ( array $topic ) use ( $unserializer ) {
			return $unserializer->unserialize( $topic, Topic::class );
		}, $json['topics'] );

		$task = new Task( $taskType, $title );
		$task->setTopics( $topics, $json['topicScores'] );
		return $task;
	}

}
