<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\TaskSuggester\QualityGateDecorator;
use MediaWiki\Json\JsonDeserializable;
use MediaWiki\Json\JsonDeserializableTrait;
use MediaWiki\Json\JsonDeserializer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Message\Message;
use MediaWiki\Title\TitleValue;
use MessageLocalizer;

/**
 * Describes a type of suggested edit.
 */
class TaskType implements JsonDeserializable {

	use JsonDeserializableTrait;

	public const DIFFICULTY_EASY = 'easy';
	public const DIFFICULTY_MEDIUM = 'medium';
	public const DIFFICULTY_HARD = 'hard';
	public const DIFFICULTY_NUMERIC = [
		1 => self::DIFFICULTY_EASY,
		2 => self::DIFFICULTY_MEDIUM,
		3 => self::DIFFICULTY_HARD
	];

	public const DIFFICULTY_CLASSES = [
		self::DIFFICULTY_EASY,
		self::DIFFICULTY_MEDIUM,
		self::DIFFICULTY_HARD,
	];

	/** Whether this is a task type generated by machine */
	protected const IS_MACHINE_SUGGESTION = false;

	/** @var string Task type ID, e.g. 'copyedit'. */
	protected $id;

	/** @var string TaskTypeHandler ID. */
	protected $handlerId;

	/** @var string Task type difficulty class, one of the DIFFICULTY_* constants. */
	protected $difficulty;

	/** @var string|null Page name to point the "learn more" link to. */
	protected $learnMoreLink;

	/** @var LinkTarget[] List of templates that prevent an article from being identified with this task type. */
	private $excludedTemplates;

	/** @var LinkTarget[] List of categories that prevent an article from being identified with this task type. */
	private $excludedCategories;

	/**
	 * @param string $id Task type ID, e.g. 'copyedit'.
	 * @param string $difficulty One of the DIFFICULTY_* constants.
	 * @param array $extraData Optional pieces of information
	 *   - 'learnMoreLink' (string): Page title for the "learn more" link for this task type.
	 * @param LinkTarget[] $excludedTemplates List of templates that prevent an article from being identified with
	 *  this task type.
	 * @param LinkTarget[] $excludedCategories List of cateogires that prevent an article from being identified with
	 *  this task type.
	 */
	public function __construct(
		$id, $difficulty, array $extraData = [], array $excludedTemplates = [], array $excludedCategories = []
	) {
		$this->id = $id;
		$this->difficulty = $difficulty;
		$this->learnMoreLink = $extraData['learnMoreLink'] ?? null;
		$this->excludedTemplates = $excludedTemplates;
		$this->excludedCategories = $excludedCategories;
	}

	/**
	 * Task type ID, e.g. 'copyedit'.
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 * @internal for use by TaskTypeHandlerRegistry only
	 */
	public function getHandlerId() {
		return $this->handlerId;
	}

	/**
	 * @param string $handlerId
	 * @internal for use by TaskTypeHandlerRegistry only
	 */
	public function setHandlerId( $handlerId ) {
		$this->handlerId = $handlerId;
	}

	/**
	 * One of the DIFFICULTY_* constants.
	 * @return string
	 */
	public function getDifficulty() {
		return $this->difficulty;
	}

	/**
	 * Page title for the "learn more" link for this task type.
	 * @return string|null
	 */
	public function getLearnMoreLink() {
		return $this->learnMoreLink;
	}

	/**
	 * @return LinkTarget[]
	 */
	public function getExcludedTemplates(): array {
		return $this->excludedTemplates;
	}

	/**
	 * @return LinkTarget[]
	 */
	public function getExcludedCategories(): array {
		return $this->excludedCategories;
	}

	/**
	 * Human-readable name of the task type.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getName( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-tasktype-name-'
			. $this->getId() );
	}

	/**
	 * Description of the task type.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getDescription( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-tasktype-description-'
			. $this->getId() );
	}

	/**
	 * Short description of the task type.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getShortDescription( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg(
			'growthexperiments-homepage-suggestededits-tasktype-shortdescription-' . $this->getId() );
	}

	/**
	 * Label for the task type; typically either the name, or a combination of the name and
	 * the short description.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getLabel( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg(
			'growthexperiments-homepage-suggestededits-tasktype-label-' . $this->getId() );
	}

	/**
	 * Time estimate for the task type.
	 * @param MessageLocalizer $messageLocalizer
	 * @return Message
	 */
	public function getTimeEstimate( MessageLocalizer $messageLocalizer ): Message {
		return $messageLocalizer->msg( 'growthexperiments-homepage-suggestededits-tasktype-time-'
			. $this->getId() );
	}

	/**
	 * Return an array (JSON-ish) representation of the task type.
	 * This is for the benefit of clients (contains details needed by a task UI) and cannot
	 * be used to recover the object.
	 * @param MessageLocalizer $messageLocalizer
	 * @return array
	 */
	public function getViewData( MessageLocalizer $messageLocalizer ) {
		$viewData = [
			'id' => $this->getId(),
			'difficulty' => $this->getDifficulty(),
			'messages' => [
				'name' => $this->getName( $messageLocalizer )->text(),
				'description' => $this->getDescription( $messageLocalizer )->parse(),
				'shortdescription' => $this->getShortDescription( $messageLocalizer )->text(),
				'label' => $this->getLabel( $messageLocalizer )->text(),
				'timeestimate' => $this->getTimeEstimate( $messageLocalizer )->text(),
			],
			'learnMoreLink' => $this->getLearnMoreLink(),
			'iconData' => $this->getIconData()
		];
		return $viewData;
	}

	/**
	 * Get icon data that should be shown for the task type
	 */
	public function getIconData(): array {
		if ( static::IS_MACHINE_SUGGESTION ) {
			return [
				// The following classes are used here:
				// * robot-task-type-easy
				// * robot-task-type-medium
				'icon' => 'robot-task-type-' . $this->getDifficulty(),
				'filterIcon' => 'robot',
				'descriptionMessageKey' => 'growthexperiments-homepage-suggestededits-tasktype-machine-description'
			];
		}
		return [];
	}

	/** @inheritDoc */
	protected function toJsonArray(): array {
		return [
			'id' => $this->getId(),
			'difficulty' => $this->getDifficulty(),
			'extraData' => [ 'learnMoreLink' => $this->getLearnMoreLink() ],
			'handlerId' => $this->getHandlerId(),
			'iconData' => $this->getIconData(),
			'excludedTemplates' => array_map( static function ( LinkTarget $excludedTemplate ) {
				return [ $excludedTemplate->getNamespace(), $excludedTemplate->getDBkey() ];
			}, $this->getExcludedTemplates() ),
			'excludedCategories' => array_map( static function ( LinkTarget $excludedCategory ) {
				return [ $excludedCategory->getNamespace(), $excludedCategory->getDBkey() ];
			}, $this->getExcludedCategories() )
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( JsonDeserializer $deserializer, array $json ) {
		$excludedTemplates = array_map( static function ( array $excludedTemplate ) {
			return new TitleValue( $excludedTemplate[0], $excludedTemplate[1] );
		}, $json['excludedTemplates'] ?? [] );
		$excludedCategories = array_map( static function ( array $excludedCategory ) {
			return new TitleValue( $excludedCategory[0], $excludedCategory[1] );
		}, $json['excludedCategories'] ?? [] );
		$taskType = new static(
			$json['id'], $json['difficulty'], $json['extraData'], $excludedTemplates, $excludedCategories
		);
		$taskType->setHandlerId( $json['handlerId'] );
		return $taskType;
	}

	/**
	 * @param array $config
	 * @return TitleValue[]
	 */
	public static function getExcludedTemplatesTitleValues( array $config ): array {
		return array_map( static function ( array $excludedTemplate ) {
			return new TitleValue( $excludedTemplate[0], $excludedTemplate[1] );
		}, $config['excludedTemplates'] ?? [] );
	}

	/**
	 * @param array $config
	 * @return TitleValue[]
	 */
	public static function getExcludedCategoriesTitleValues( array $config ): array {
		return array_map( static function ( array $excludedCategory ) {
			return new TitleValue( $excludedCategory[0], $excludedCategory[1] );
		}, $config['excludedCategories'] ?? [] );
	}

	/**
	 * Whether the corresponding article for the task type should be opened in edit mode
	 */
	public function shouldOpenInEditMode(): bool {
		return false;
	}

	/**
	 * Get the default edit section for the task type
	 */
	public function getDefaultEditSection(): string {
		return '';
	}

	/**
	 * Get CSS classes to add to the small task card image element.
	 */
	public function getSmallTaskCardImageCssClasses(): array {
		return [ 'mw-ge-small-task-card-image-skeleton' ];
	}

	/**
	 * The quality gate data for this task type.
	 *
	 * @see QualityGateDecorator and modules/ext.growthExperiments.Homepage.SuggestedEdits/QualityGate.js
	 * @return string[] An array of quality gate names that will be applied for the task type.
	 */
	public function getQualityGateIds(): array {
		return [];
	}

	/**
	 * Return the filters to apply to the recommendation
	 */
	public function getSuggestionFilters(): array {
		return [];
	}
}
