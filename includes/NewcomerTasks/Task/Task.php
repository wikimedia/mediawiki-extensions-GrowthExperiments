<?php

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use MediaWiki\Json\JsonDeserializable;
use MediaWiki\Json\JsonDeserializableTrait;
use MediaWiki\Json\JsonDeserializer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleValue;

/**
 * A single task recommendation.
 * A Task specifies a page and the type of the task to perform on it.
 */
class Task implements JsonDeserializable {

	use JsonDeserializableTrait;

	/** @var TaskType */
	private $taskType;

	/** @var LinkTarget The page to edit. */
	private $title;

	/** @var Topic[] */
	private $topics = [];

	/** @var string unique task identifier for analytics purposes */
	private $token;

	/**
	 * @param TaskType $taskType
	 * @param LinkTarget $title The page this task is about.
	 * @param string|null $token
	 */
	public function __construct( TaskType $taskType, LinkTarget $title, ?string $token = null ) {
		$this->taskType = $taskType;
		$this->title = $title;
		$this->token = $token ?? Util::generateRandomToken();
	}

	public function getToken(): string {
		return $this->token;
	}

	public function setToken( string $token ): void {
		$this->token = $token;
	}

	public function getTaskType(): TaskType {
		return $this->taskType;
	}

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
	 * @param Topic[] $topics
	 */
	public function setTopics( array $topics ): void {
		$this->topics = $topics;
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
			'token' => $this->getToken()
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonDeserializer $deserializer, array $json ) {
		# T312589: In the future JsonCodec will take care of deserializing
		# the values in the $json array itself.
		$taskType = $json['taskType'] instanceof TaskType ?
			$json['taskType'] :
			$deserializer->deserialize( $json['taskType'], TaskType::class );
		$title = new TitleValue( $json['title'][0], $json['title'][1] );
		$topics = array_map( static function ( $topic ) use ( $deserializer ) {
			return $topic instanceof Topic ? $topic :
				$deserializer->deserialize( $topic, Topic::class );
		}, $json['topics'] );

		$task = new static( $taskType, $title, $json['token'] ?? null );
		$task->setTopics( $topics );
		return $task;
	}

}
