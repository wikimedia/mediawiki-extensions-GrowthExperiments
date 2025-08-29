<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Message\Message;
use MessageLocalizer;
use Wikimedia\Message\ListType;
use Wikimedia\Message\MessageSpecifier;

class SectionImageRecommendationTaskTypeHandler extends ImageRecommendationBaseTaskTypeHandler {
	public const ID = 'section-image-recommendation';

	public const TASK_TYPE_ID = 'section-image-recommendation';

	public const CHANGE_TAG = 'newcomer task section image suggestion';

	/** The tag prefix used for CirrusSearch\Wikimedia\WeightedTags. */
	public const WEIGHTED_TAG_PREFIX = 'recommendation.image_section';

	/** @inheritDoc */
	public function createTaskType( string $taskTypeId, array $config ): SectionImageRecommendationTaskType {
		$extraData = [ 'learnMoreLink' => $config['learnmore'] ?? null ];
		$settings = array_intersect_key( $config, SectionImageRecommendationTaskType::DEFAULT_SETTINGS );
		$taskType = new SectionImageRecommendationTaskType(
			$taskTypeId,
			$config['group'],
			$settings,
			$extraData,
			$this->parseExcludedTemplates( $config ),
			$this->parseExcludedCategories( $config )
		);
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	/** @inheritDoc */
	public function getSearchTerm( TaskType $taskType ): string {
		if ( $taskType->getHandlerId() !== self::ID ) {
			throw new InvalidArgumentException( '$taskType must be a section image recommendation task type' );
		}
		// T329396 makeshift solution to avoid section image recommendations displacing potentially more
		// valuable top-level image recommendation tasks
		return parent::getSearchTerm( $taskType ) . 'hasrecommendation:image_section -hasrecommendation:image';
	}

	/** @inheritDoc */
	public function getSubmitDataFormatMessage(
		TaskType $taskType,
		MessageLocalizer $localizer
	): MessageSpecifier {
		if ( !( $taskType instanceof SectionImageRecommendationTaskType ) ) {
			throw new LogicException( 'impossible' );
		}
		$wrappedReasons = array_map(
			static fn ( $reason ) => "<kbd>$reason</kbd>",
			AddImageSubmissionHandler::REJECTION_REASONS
		);
		return $localizer->msg(
			'apihelp-growthexperiments-structured-task-submit-data-format-section-image-recommendation',
			Message::listParam( $wrappedReasons, ListType::COMMA ),
			Message::numParam( $taskType->getMinimumCaptionCharacterLength() )
		);
	}
}
