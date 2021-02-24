<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use Wikimedia\LightweightObjectStore\ExpirationAwareness;

class LinkRecommendationTaskType extends TaskType {

	/** @see ::getMinimumTasksPerTopic */
	public const FIELD_MIN_TASKS_PER_TOPIC = 'minimumTasksPerTopic';
	/** @see :getMinimumLinksPerTask */
	public const FIELD_MIN_LINKS_PER_TASK = 'minimumLinksPerTask';
	/** @see :getMinimumLinkScore */
	public const FIELD_MIN_LINK_SCORE = 'minimumLinkScore';
	/** @see :getMaximumLinksPerTask */
	public const FIELD_MAX_LINKS_PER_TASK = 'maximumLinksPerTask';
	/** @see :getMinimumTimeSinceLastEdit */
	public const FIELD_MIN_TIME_SINCE_LAST_EDIT = 'minimumTimeSinceLastEdit';
	/** @see :getMinimumWordCount */
	public const FIELD_MIN_WORD_COUNT = 'minimumWordCount';
	/** @see :getMaximumWordCount */
	public const FIELD_MAX_WORD_COUNT = 'maximumWordCount';

	public const DEFAULT_SETTINGS = [
		self::FIELD_MIN_TASKS_PER_TOPIC => 500,
		self::FIELD_MIN_LINKS_PER_TASK => 4,
		self::FIELD_MIN_LINK_SCORE => 0.5,
		self::FIELD_MAX_LINKS_PER_TASK => 10,
		self::FIELD_MIN_TIME_SINCE_LAST_EDIT => ExpirationAwareness::TTL_DAY,
		self::FIELD_MIN_WORD_COUNT => 0,
		self::FIELD_MAX_WORD_COUNT => PHP_INT_MAX,
	];

	/** @var int */
	protected $minimumTasksPerTopic;
	/** @var int */
	protected $minimumLinksPerTask;
	/** @var float */
	protected $minimumLinkScore;
	/** @var int */
	protected $maximumLinksPerTask;
	/** @var int */
	protected $minimumTimeSinceLastEdit;
	/** @var int */
	protected $minimumWordCount;
	/** @var int */
	protected $maximumWordCount;

	/**
	 * @inheritDoc
	 * @param array $settings A settings array matching LinkRecommendationTaskType::DEFAULT_SETTINGS.
	 */
	public function __construct( $id, $difficulty, array $settings, array $extraData = [] ) {
		parent::__construct( $id, $difficulty, $extraData );
		$settings += self::DEFAULT_SETTINGS;
		$this->minimumTasksPerTopic = $settings[self::FIELD_MIN_TASKS_PER_TOPIC];
		$this->minimumLinksPerTask = $settings[self::FIELD_MIN_LINKS_PER_TASK];
		$this->minimumLinkScore = $settings[self::FIELD_MIN_LINK_SCORE];
		$this->maximumLinksPerTask = $settings[self::FIELD_MAX_LINKS_PER_TASK];
		$this->minimumTimeSinceLastEdit = $settings[self::FIELD_MIN_TIME_SINCE_LAST_EDIT];
		$this->minimumWordCount = $settings[self::FIELD_MIN_WORD_COUNT];
		$this->maximumWordCount = $settings[self::FIELD_MAX_WORD_COUNT];
	}

	/**
	 * Try to have at least this many link recommendations prepared for each ORES topic.
	 * Recommendations are filled up every hour to this level.
	 * Note: these are individual ORES topics, not the combined Growth topics defined via
	 * $wgGENewcomerTasksOresTopicConfigTitle.
	 * @return int
	 */
	public function getMinimumTasksPerTopic(): int {
		return $this->minimumTasksPerTopic;
	}

	/**
	 * Each recommendation must contain at least this many suggested links.
	 * @return int
	 */
	public function getMinimumLinksPerTask(): int {
		return $this->minimumLinksPerTask;
	}

	/**
	 * Required confidence score for each link.
	 * @return float
	 */
	public function getMinimumLinkScore(): float {
		return $this->minimumLinkScore;
	}

	/**
	 * Don't show more than this many link recommendations at the same time.
	 * @return int
	 */
	public function getMaximumLinksPerTask(): int {
		return $this->maximumLinksPerTask;
	}

	/**
	 * At least this much time (in seconds) needs to have passed since the article was last edited.
	 * @return int
	 */
	public function getMinimumTimeSinceLastEdit(): int {
		return $this->minimumTimeSinceLastEdit;
	}

	/**
	 * Only use articles with this at least many words for link recommendations.
	 * (The word count will be some naive wikitext-based estimation.)
	 * @return int
	 */
	public function getMinimumWordCount(): int {
		return $this->minimumWordCount;
	}

	/**
	 * Only use articles with this at most many words for link recommendations.
	 * (The word count will be some naive wikitext-based estimation.)
	 * @return int
	 */
	public function getMaximumWordCount(): int {
		return $this->maximumWordCount;
	}

	/** @inheritDoc */
	public function getIconName(): string {
		return 'robot-task-type';
	}

}
