<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Json\JsonDeserializer;
use MessageLocalizer;
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
	/** @see :getMaximumLinksToShowPerTask */
	public const FIELD_MAX_LINKS_TO_SHOW_PER_TASK = 'maximumLinksToShowPerTask';
	/** @see :getMinimumTimeSinceLastEdit */
	public const FIELD_MIN_TIME_SINCE_LAST_EDIT = 'minimumTimeSinceLastEdit';
	/** @see :getMinimumWordCount */
	public const FIELD_MIN_WORD_COUNT = 'minimumWordCount';
	/** @see :getMaximumWordCount */
	public const FIELD_MAX_WORD_COUNT = 'maximumWordCount';
	/** @see :getMaxTasksPerDay */
	public const FIELD_MAX_TASKS_PER_DAY = 'maxTasksPerDay';
	/** @see :getExcludedSections */
	public const FIELD_EXCLUDED_SECTIONS = 'excludedSections';
	/** @see :getUnderlinkedWeight */
	public const FIELD_UNDERLINKED_WEIGHT = 'underlinkedWeight';
	/** @see :getUnderlinkedMinLength */
	public const FIELD_UNDERLINKED_MIN_LENGTH = 'underlinkedMinLength';

	/** Exclude a (task page, target page) pair from future tasks after this many rejections. */
	public const REJECTION_EXCLUSION_LIMIT = 2;

	public const DEFAULT_SETTINGS = [
		self::FIELD_MIN_TASKS_PER_TOPIC => 500,
		self::FIELD_MIN_LINKS_PER_TASK => 2,
		self::FIELD_MIN_LINK_SCORE => 0.6,
		self::FIELD_MAX_LINKS_PER_TASK => 10,
		self::FIELD_MAX_LINKS_TO_SHOW_PER_TASK => 3,
		self::FIELD_MIN_TIME_SINCE_LAST_EDIT => ExpirationAwareness::TTL_DAY,
		self::FIELD_MIN_WORD_COUNT => 0,
		self::FIELD_MAX_WORD_COUNT => PHP_INT_MAX,
		self::FIELD_MAX_TASKS_PER_DAY => 25,
		self::FIELD_EXCLUDED_SECTIONS => [],
		self::FIELD_UNDERLINKED_WEIGHT => 0.5,
		self::FIELD_UNDERLINKED_MIN_LENGTH => 300,
	];

	/** @inheritDoc */
	protected const IS_MACHINE_SUGGESTION = true;

	/** @var int */
	protected $minimumTasksPerTopic;
	/** @var int */
	protected $minimumLinksPerTask;
	/** @var float */
	protected $minimumLinkScore;
	/** @var int */
	protected $maximumLinksPerTask;
	/** @var int */
	protected $maximumLinksToShowPerTask;
	/** @var int */
	protected $minimumTimeSinceLastEdit;
	/** @var int */
	protected $minimumWordCount;
	/** @var int */
	protected $maximumWordCount;
	/** @var int */
	protected $maxTasksPerDay;
	/** @var string[] */
	protected $excludedSections;
	/** @var float */
	protected $underlinkedWeight;
	/** @var int */
	protected $underlinkedMinLength;

	/**
	 * @inheritDoc
	 * @param array $settings A settings array matching LinkRecommendationTaskType::DEFAULT_SETTINGS.
	 */
	public function __construct(
		$id,
		$difficulty,
		array $settings = [],
		array $extraData = [],
		array $excludedTemplates = [],
		array $excludedCategories = []
	) {
		parent::__construct( $id, $difficulty, $extraData, $excludedTemplates, $excludedCategories );
		$settings += self::DEFAULT_SETTINGS;
		$this->minimumTasksPerTopic = $settings[self::FIELD_MIN_TASKS_PER_TOPIC];
		$this->minimumLinksPerTask = $settings[self::FIELD_MIN_LINKS_PER_TASK];
		$this->minimumLinkScore = $settings[self::FIELD_MIN_LINK_SCORE];
		$this->maximumLinksPerTask = $settings[self::FIELD_MAX_LINKS_PER_TASK];
		$this->maximumLinksToShowPerTask = $settings[self::FIELD_MAX_LINKS_TO_SHOW_PER_TASK];
		$this->minimumTimeSinceLastEdit = $settings[self::FIELD_MIN_TIME_SINCE_LAST_EDIT];
		$this->minimumWordCount = $settings[self::FIELD_MIN_WORD_COUNT];
		$this->maximumWordCount = $settings[self::FIELD_MAX_WORD_COUNT];
		$this->maxTasksPerDay = $settings[self::FIELD_MAX_TASKS_PER_DAY];
		$this->excludedSections = $settings[self::FIELD_EXCLUDED_SECTIONS];
		$this->underlinkedWeight = $settings[self::FIELD_UNDERLINKED_WEIGHT];
		$this->underlinkedMinLength = $settings[self::FIELD_UNDERLINKED_MIN_LENGTH];
	}

	/**
	 * Try to have at least this many link recommendations prepared for each ORES topic.
	 * Recommendations are filled up every hour to this level.
	 * Note: these are individual ORES topics, not the combined Growth topics defined via
	 * $wgGENewcomerTasksOresTopicConfig.
	 */
	public function getMinimumTasksPerTopic(): int {
		return $this->minimumTasksPerTopic;
	}

	/**
	 * Each recommendation must contain at least this many suggested links.
	 */
	public function getMinimumLinksPerTask(): int {
		return $this->minimumLinksPerTask;
	}

	/**
	 * Required confidence score for each link.
	 */
	public function getMinimumLinkScore(): float {
		return $this->minimumLinkScore;
	}

	/**
	 * The maximum number of links that the refreshLinkRecommendations maintenance script will
	 * request when calling the link recommendation service for an article.
	 *
	 * @see ServiceLinkRecommendationProvider::get()
	 * @return int
	 */
	public function getMaximumLinksPerTask(): int {
		return $this->maximumLinksPerTask;
	}

	/**
	 * The maximum number of link recommendations that will be shown in the AddLink plugin to VisualEditor.
	 * @see AddLinkArticleTarget.js#annotateSuggestions
	 * @return int
	 */
	public function getMaximumLinksToShowPerTask(): int {
		return (int)$this->maximumLinksToShowPerTask;
	}

	/**
	 * At least this much time (in seconds) needs to have passed since the article was last edited.
	 */
	public function getMinimumTimeSinceLastEdit(): int {
		return $this->minimumTimeSinceLastEdit;
	}

	/**
	 * Only use articles with this at least many words for link recommendations.
	 * (The word count will be some naive wikitext-based estimation.)
	 */
	public function getMinimumWordCount(): int {
		return $this->minimumWordCount;
	}

	/**
	 * Only use articles with this at most many words for link recommendations.
	 * (The word count will be some naive wikitext-based estimation.)
	 */
	public function getMaximumWordCount(): int {
		return $this->maximumWordCount;
	}

	/**
	 * The maximum number of image recommendation tasks that a user can perform each calendar day.
	 */
	public function getMaxTasksPerDay(): int {
		return $this->maxTasksPerDay;
	}

	/**
	 * The list of sections which should be excluded when recommending links.
	 *
	 * @return string[]
	 */
	public function getExcludedSections(): array {
		return $this->excludedSections;
	}

	/**
	 * Weight of the underlinkedness metric (vs. a random factor) in sorting.
	 * Higher is less random. E.g. a weight of 0.25 means the scoring function will be
	 * 0.25 * <underlinkedness> + 0.75 * random(0,1).
	 */
	public function getUnderlinkedWeight(): float {
		return $this->underlinkedWeight;
	}

	/**
	 * Minimum length above which an article can be considered underlinked.
	 * If the article size is smaller than this, its underlinkedness score will be 0.
	 * FIXME is this useful given that we already have a min words limit?
	 */
	public function getUnderlinkedMinLength(): int {
		return $this->underlinkedMinLength;
	}

	/** @inheritDoc */
	public function shouldOpenInEditMode(): bool {
		return true;
	}

	/** @inheritDoc */
	public function getDefaultEditSection(): string {
		return 'all';
	}

	/** @inheritDoc */
	public function getQualityGateIds(): array {
		return [ 'dailyLimit' ];
	}

	/** @inheritDoc */
	public function getViewData( MessageLocalizer $messageLocalizer ): array {
		return parent::getViewData( $messageLocalizer ) + [
			self::FIELD_MAX_LINKS_TO_SHOW_PER_TASK => $this->getMaximumLinksToShowPerTask()
		];
	}

	/** @inheritDoc */
	protected function toJsonArray(): array {
		return parent::toJsonArray() + [
				'settings' => [
					'minimumTasksPerTopic' => $this->minimumTasksPerTopic,
					'minimumLinksPerTask' => $this->minimumLinksPerTask,
					'minimumLinkScore' => $this->minimumLinkScore,
					'maximumLinksPerTask' => $this->maximumLinksPerTask,
					'maximumLinksToShowPerTask' => $this->maximumLinksToShowPerTask,
					'minimumTimeSinceLastEdit' => $this->minimumTimeSinceLastEdit,
					'minimumWordCount' => $this->minimumWordCount,
					'maximumWordCount' => $this->maximumWordCount,
					'maxTasksPerDay' => $this->maxTasksPerDay,
					'underlinkedWeight' => $this->underlinkedWeight,
					'underlinkedMinLength' => $this->underlinkedMinLength,
				],
			];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonDeserializer $deserializer, array $json ) {
		$taskType = new LinkRecommendationTaskType(
			$json['id'],
			$json['difficulty'],
			$json['settings'],
			$json['extraData'],
			self::getExcludedTemplatesTitleValues( $json ),
			self::getExcludedCategoriesTitleValues( $json )
		);
		$taskType->setHandlerId( $json['handlerId'] );
		return $taskType;
	}

}
