<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use LogicException;
use MediaWiki\Title\TitleParser;
use StatusValue;

abstract class ImageRecommendationBaseTaskTypeHandler extends StructuredTaskTypeHandler {

	// This weird hack is the only way in PHP to have abstract constants.
	public const ID = self::ID;
	public const TASK_TYPE_ID = self::TASK_TYPE_ID;
	public const CHANGE_TAG = self::CHANGE_TAG;
	public const WEIGHTED_TAG_PREFIX = self::WEIGHTED_TAG_PREFIX;

	protected readonly ImageRecommendationProvider $recommendationProvider;
	protected readonly AddImageSubmissionHandler $submissionHandler;

	public function __construct(
		ConfigurationValidator $configurationValidator,
		TitleParser $titleParser,
		ImageRecommendationProvider $recommendationProvider,
		AddImageSubmissionHandler $submissionHandler
	) {
		parent::__construct( $configurationValidator, $titleParser );
		$this->recommendationProvider = $recommendationProvider;
		$this->submissionHandler = $submissionHandler;
	}

	/** @inheritDoc */
	public function getId(): string {
		return static::ID;
	}

	/** @inheritDoc */
	public function getChangeTags( ?string $taskType = null ): array {
		return [ TaskTypeHandler::NEWCOMER_TASK_TAG, static::CHANGE_TAG ];
	}

	/** @inheritDoc */
	public function getRecommendationProvider(): ImageRecommendationProvider {
		return $this->recommendationProvider;
	}

	/** @inheritDoc */
	public function getSubmissionHandler(): AddImageSubmissionHandler {
		return $this->submissionHandler;
	}

	/** @inheritDoc */
	public function validateTaskTypeConfiguration( string $taskTypeId, array $config ): StatusValue {
		$status = parent::validateTaskTypeConfiguration( $taskTypeId, $config );
		if ( !$status->isOK() ) {
			return $status;
		}
		foreach ( [
			ImageRecommendationBaseTaskType::FIELD_MAX_TASKS_PER_DAY,
			ImageRecommendationBaseTaskType::FIELD_MINIMUM_CAPTION_CHARACTER_LENGTH,
		] as $field ) {
			if ( array_key_exists( $field, $config ) ) {
				$status->merge( $this->configurationValidator->validateInteger(
					$config, $field, $taskTypeId, 1 ) );
			}
		}
		return $status;
	}

	/** @inheritDoc */
	public function getTaskTypeIdByChangeTagName( string $changeTagName ): ?string {
		if ( $changeTagName !== static::CHANGE_TAG ) {
			throw new LogicException( "\"$changeTagName\" is not a valid change tag name for " . static::class );
		}
		return static::TASK_TYPE_ID;
	}

}
