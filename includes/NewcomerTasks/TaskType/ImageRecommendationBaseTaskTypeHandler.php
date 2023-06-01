<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use LogicException;
use StatusValue;
use TitleParser;
use Wikimedia\Assert\Assert;

abstract class ImageRecommendationBaseTaskTypeHandler extends StructuredTaskTypeHandler {

	// This weird hack is the only way in PHP to have abstract constants.
	public const ID = self::ID;
	public const TASK_TYPE_ID = self::TASK_TYPE_ID;
	public const CHANGE_TAG = self::CHANGE_TAG;
	public const WEIGHTED_TAG_PREFIX = self::WEIGHTED_TAG_PREFIX;

	protected ImageRecommendationProvider $recommendationProvider;
	protected SubmissionHandler $submissionHandler;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TitleParser $titleParser
	 * @param ImageRecommendationProvider $recommendationProvider
	 * @param SubmissionHandler $submissionHandler
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TitleParser $titleParser,
		ImageRecommendationProvider $recommendationProvider,
		// FIXME narrow type once the submission handlers are merged
		SubmissionHandler $submissionHandler
	) {
		parent::__construct( $configurationValidator, $titleParser );
		Assert::parameterType( ImageRecommendationProvider::class, $recommendationProvider,
			'$recommendationProvider' );
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
	public function getSubmissionHandler(): SubmissionHandler {
		// FIXME narrow return type once the submission handlers are merged
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
