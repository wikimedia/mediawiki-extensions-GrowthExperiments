<?php

namespace GrowthExperiments\NewcomerTasks\Task;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use GrowthExperiments\Util;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleValue;
use Wikimedia\JsonCodec\Hint;
use Wikimedia\JsonCodec\JsonCodecable;
use Wikimedia\JsonCodec\JsonCodecableTrait;

/**
 * A single task recommendation.
 * A Task specifies a page and the type of the task to perform on it.
 */
class Task implements JsonCodecable {

	use JsonCodecableTrait;

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
	public function toJsonArray(): array {
		return [
			'taskType' => $this->getTaskType(),
			'title' => [ $this->getTitle()->getNamespace(), $this->getTitle()->getDBkey() ],
			'topics' => $this->getTopics(),
			'token' => $this->getToken(),
		];
	}

	/** @inheritDoc */
	public static function jsonClassHintFor( string $keyName ) {
		return match ( $keyName ) {
			'taskType' => Hint::build( TaskType::class, Hint::ONLY_FOR_DECODE ),
			'topics' => Hint::build( Topic::class, Hint::ONLY_FOR_DECODE, Hint::LIST ),
			default => null,
		};
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): self {
		$title = new TitleValue( $json['title'][0], $json['title'][1] );

		$task = new static( $json['taskType'], $title, $json['token'] ?? null );
		$task->setTopics( $json['topics'] );
		return $task;
	}

}
