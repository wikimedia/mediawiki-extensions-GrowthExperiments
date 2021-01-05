<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use InvalidArgumentException;

class LinkRecommendationTaskTypeHandler extends TaskTypeHandler {

	public const ID = 'link-recommendation';

	public const CHANGE_TAG = 'newcomer task add link';

	/** @inheritDoc */
	public function getId(): string {
		return self::ID;
	}

	/** @inheritDoc */
	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		$extraData = [ 'learnMoreLink' => $config['learnmore'] ?? null ];
		$settings = array_intersect_key( $config, LinkRecommendationTaskType::DEFAULT_SETTINGS );
		$taskType = new LinkRecommendationTaskType( $taskTypeId, $config['group'], $settings, $extraData );
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	/** @inheritDoc */
	public function getSearchTerm( TaskType $taskType ): string {
		if ( $taskType->getHandlerId() !== self::ID ) {
			throw new InvalidArgumentException( '$taskType must be a link recommendation task type' );
		}
		return 'hasrecommendation:link';
	}

	/** @inheritDoc */
	public function getChangeTags(): array {
		return [ TaskTypeHandler::NEWCOMER_TASK_TAG, self::CHANGE_TAG ];
	}

}
