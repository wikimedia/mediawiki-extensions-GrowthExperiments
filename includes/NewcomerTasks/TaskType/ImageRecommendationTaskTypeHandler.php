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

class ImageRecommendationTaskTypeHandler extends ImageRecommendationBaseTaskTypeHandler {

	public const ID = 'image-recommendation';

	public const TASK_TYPE_ID = 'image-recommendation';

	public const CHANGE_TAG = 'newcomer task image suggestion';

	/** The tag prefix used for CirrusSearch\Wikimedia\WeightedTags. */
	public const WEIGHTED_TAG_PREFIX = 'recommendation.image';

	/** @inheritDoc */
	public function createTaskType( string $taskTypeId, array $config ): ImageRecommendationTaskType {
		$extraData = [ 'learnMoreLink' => $config['learnmore'] ?? null ];
		$settings = array_intersect_key( $config, ImageRecommendationTaskType::DEFAULT_SETTINGS );
		$taskType = new ImageRecommendationTaskType(
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
			throw new InvalidArgumentException( '$taskType must be an image recommendation task type' );
		}
		return parent::getSearchTerm( $taskType ) . 'hasrecommendation:image -hastemplatecollection:infobox';
	}

	/** @inheritDoc */
	public function getSubmitDataFormatMessage(
		TaskType $taskType,
		MessageLocalizer $localizer
	): MessageSpecifier {
		if ( !( $taskType instanceof ImageRecommendationTaskType ) ) {
			throw new LogicException( 'impossible' );
		}
		$wrappedReasons = array_map(
			static fn ( $reason ) => "<kbd>$reason</kbd>",
			AddImageSubmissionHandler::REJECTION_REASONS
		);
		return $localizer->msg(
			'apihelp-growthexperiments-structured-task-submit-data-format-image-recommendation',
			Message::listParam( $wrappedReasons, ListType::COMMA ),
			Message::numParam( $taskType->getMinimumCaptionCharacterLength() )
		);
	}

}
