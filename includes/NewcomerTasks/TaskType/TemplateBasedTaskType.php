<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use MediaWiki\Linker\LinkTarget;

class TemplateBasedTaskType extends TaskType {

	/**
	 * List of templates any one of which identifies a wiki page as a candidate for this task type.
	 * @var LinkTarget[]
	 */
	private $templates;

	/**
	 * @param string $id Task type ID, e.g. 'copyedit'.
	 * @param string $difficulty One of the DIFFICULTY_* constants.
	 * @param array $extraData See TaskType::__construct()
	 * @param LinkTarget[] $templates
	 * @param LinkTarget[] $excludedTemplates
	 * @param LinkTarget[] $excludedCategories
	 */
	public function __construct(
		$id,
		$difficulty,
		array $extraData,
		array $templates,
		array $excludedTemplates = [],
		array $excludedCategories = []
	) {
		parent::__construct( $id, $difficulty, $extraData, $excludedTemplates, $excludedCategories );
		$this->templates = $templates;
	}

	/**
	 * @return LinkTarget[]
	 */
	public function getTemplates(): array {
		return $this->templates;
	}

	/** @inheritDoc */
	public function toJsonArray(): array {
		return parent::toJsonArray() + [
			'templates' => self::linkTargetToJsonArray(
				$this->getTemplates()
			),
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): self {
		$taskType = new static(
			$json['id'],
			$json['difficulty'],
			$json['extraData'],
			self::newTitleValuesFromJsonArray(
				$json['templates']
			),
			self::getExcludedTemplatesTitleValues( $json ),
			self::getExcludedCategoriesTitleValues( $json )
		);
		$taskType->setHandlerId( $json['handlerId'] );
		return $taskType;
	}

}
