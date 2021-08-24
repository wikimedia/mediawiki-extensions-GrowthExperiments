<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use InvalidArgumentException;

class ImageRecommendationTaskTypeHandler extends TaskTypeHandler {

	public const ID = 'image-recommendation';

	public const TASK_TYPE_ID = 'image-recommendation';

	public const CHANGE_TAG = 'newcomer task image suggestion';

	/** The tag prefix used for CirrusSearch\Wikimedia\WeightedTags. */
	public const WEIGHTED_TAG_PREFIX = 'recommendation.image';

	/** @inheritDoc */
	public function getId(): string {
		return self::ID;
	}

	/** @inheritDoc */
	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		$extraData = [ 'learnMoreLink' => $config['learnmore'] ?? null ];
		$taskType = new ImageRecommendationTaskType( $taskTypeId, $config['group'], $extraData );
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	/** @inheritDoc */
	public function getSearchTerm( TaskType $taskType ): string {
		if ( $taskType->getHandlerId() !== self::ID ) {
			throw new InvalidArgumentException( '$taskType must be an image recommendation task type' );
		}
		return 'hasrecommendation:image';
	}

	/** @inheritDoc */
	public function getChangeTags(): array {
		return [ TaskTypeHandler::NEWCOMER_TASK_TAG, self::CHANGE_TAG ];
	}
}
