<?php

namespace GrowthExperiments\HelpPanel\Tips;

abstract class TipTree {

	public const TIP_DATA_TYPE_PLAIN_MESSAGE = 'message';
	public const TIP_DATA_TYPE_TEXT_VARIANT = 'text-variant';
	public const TIP_DATA_TYPE_IMAGE = 'image';
	public const TIP_DATA_TYPE_OOUI_ICON = 'icon';
	public const TIP_DATA_TYPE_TITLE = 'title';
	public const TIP_TYPE_MAIN_MULTIPLE_MAX_NODES = 3;

	// The tip step names to use for constructing guidance tips in the help
	// panel's suggested edit screen. Hard-coded for now.
	private const TIP_STEP_NAMES = [
		'value',
		'calm',
		'rules1',
		'rules2',
		'step1',
		'step2',
		'step3',
		'publish'
	];

	// The types of tips that can be shown for a task type. Hard-coded for now.
	private const TIP_TYPES = [
		'header',
		'main',
		'main-multiple',
		'example',
		'graphic',
		'text'
	];

	protected ?string $learnMoreLink = null;
	protected array $extraData;

	public function __construct( array $extraData ) {
		$this->learnMoreLink = $extraData[$this->getTaskTypeId()]['learnMoreLink'] ?? null;
		$this->extraData = $extraData;
	}

	/**
	 * @return string[]
	 */
	public function getStepNames() {
		return self::TIP_STEP_NAMES;
	}

	/**
	 * @return string[]
	 */
	public function getTipTypes() {
		return self::TIP_TYPES;
	}

	/**
	 * Get tip steps that will be used to build a node tree for a task type.
	 * @return array
	 */
	abstract public function getTree(): array;

	/**
	 * Get the task type ID that corresponds to this tip tree class.
	 * @return string
	 */
	abstract protected function getTaskTypeId(): string;

	/**
	 * @return array|string[]
	 */
	protected function getEditMessageTipConfigData(): array {
		return [
			'type' => self::TIP_DATA_TYPE_PLAIN_MESSAGE,
			'data' => 'vector-view-edit',
			'variant' => [
				'minerva' => [
					'data' => 'mobile-frontend-editor-edit',
				]
			]
		];
	}

	/**
	 * @return array|string[]
	 */
	protected function getPublishMessageTipConfigData(): array {
		return [
			'type' => self::TIP_DATA_TYPE_PLAIN_MESSAGE,
			'data' => 'publishchanges-start'
		];
	}

	/**
	 * @param array $steps
	 * @param string $stepName Step to which learn more link is added
	 * @param string $tipType Tip type to which learn more link is added
	 * @return array
	 */
	protected function maybeAddLearnMoreLinkTipNode(
		array $steps,
		string $stepName = 'publish',
		string $tipType = 'text'
	): array {
		if ( $this->getLearnMoreLink() ) {
			$steps[$stepName][$tipType] = [
				[
					'type' => self::TIP_DATA_TYPE_TITLE,
					'data' => [ 'title' => $this->getLearnMoreLink() ],
				]
			];
		}
		return $steps;
	}

	/**
	 * @return string|null
	 */
	protected function getLearnMoreLink() {
		return $this->learnMoreLink;
	}
}
