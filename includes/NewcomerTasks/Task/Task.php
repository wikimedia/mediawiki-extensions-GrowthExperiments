<?php

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
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

	/** @var string unique task identifier for analytics purposes */
	private $token;

	/**
	 * @param TaskType $taskType
	 * @param LinkTarget $title The page this task is about.
	 * @param string|null $token
	 */
	public function __construct( TaskType $taskType, LinkTarget $title, string $token = null ) {
		$this->taskType = $taskType;
		$this->title = $title;
		$this->token = $token ?? Util::generateRandomToken();
	}

	/**
	 * @return string
	 */
	public function getToken(): string {
		return $this->token;
	}

	/**
	 * @param string $token
	 */
	public function setToken( string $token ): void {
		$this->token = $token;
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
		# T312589 explicitly calling jsonSerialize() will be unnecessary
		# in the future.
		return [
			'taskType' => $this->getTaskType()->jsonSerialize(),
			'title' => [ $this->getTitle()->getNamespace(), $this->getTitle()->getDBkey() ],
			'topics' => array_map( static function ( Topic $topic ) {
				return $topic->jsonSerialize();
			}, $this->getTopics() ),
			'topicScores' => $this->getTopicScores(),
			'token' => $this->getToken()
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonUnserializer $unserializer, array $json ) {
		# T312589: In the future JsonCodec will take care of unserializing
		# the values in the $json array itself.
		$taskType = $json['taskType'] instanceof TaskType ?
			$json['taskType'] :
			$unserializer->unserialize( $json['taskType'], TaskType::class );
		$title = new TitleValue( $json['title'][0], $json['title'][1] );
		$topics = array_map( static function ( $topic ) use ( $unserializer ) {
			return $topic instanceof Topic ? $topic :
				$unserializer->unserialize( $topic, Topic::class );
		}, $json['topics'] );

		$task = new static( $taskType, $title, $json['token'] ?? null );
		$task->setTopics( $topics, $json['topicScores'] );
		return $task;
	}

}
