<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

/**
 * A "fake" task type which only exists to reuse task type related search functionality
 * when searching for task type candidates which are not task types yet. i18n-related methods
 * should not be expected to provide anything meaningful.
 */
class NullTaskType extends TaskType {

	/** @var string */
	private $extraSearchConditions;

	/**
	 * @param string $id Task ID.
	 * @param string $extraSearchConditions Extra conditions to append to the search query.
	 */
	public function __construct( $id, string $extraSearchConditions = '' ) {
		parent::__construct( $id, TaskType::DIFFICULTY_EASY );
		$this->extraSearchConditions = $extraSearchConditions;
	}

	/**
	 * Extra conditions to append to the search query.
	 */
	public function getExtraSearchConditions(): string {
		return $this->extraSearchConditions;
	}

	/** @inheritDoc */
	public function toJsonArray(): array {
		return parent::toJsonArray() + [
			'extraSearchConditions' => $this->extraSearchConditions,
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): self {
		return new static( $json['id'], $json['extraSearchConditions'] );
	}

}
