<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\AddSectionImage\AddSectionImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use InvalidArgumentException;
use MessageLocalizer;
use MessageSpecifier;
use TitleParser;

class SectionImageRecommendationTaskTypeHandler extends StructuredTaskTypeHandler {
	public const ID = 'section-image-recommendation';

	public const TASK_TYPE_ID = 'section-image-recommendation';

	public const CHANGE_TAG = 'newcomer task section image suggestion';

	/** The tag prefix used for CirrusSearch\Wikimedia\WeightedTags. */
	public const WEIGHTED_TAG_PREFIX = 'recommendation.image_section';

	private ImageRecommendationProvider $recommendationProvider;
	private AddSectionImageSubmissionHandler $submissionHandler;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TitleParser $titleParser
	 * @param ImageRecommendationProvider $recommendationProvider
	 * @param AddSectionImageSubmissionHandler $submissionHandler
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TitleParser $titleParser,
		ImageRecommendationProvider $recommendationProvider,
		AddSectionImageSubmissionHandler $submissionHandler
	) {
		parent::__construct( $configurationValidator, $titleParser );
		$this->recommendationProvider = $recommendationProvider;
		$this->submissionHandler = $submissionHandler;
	}

	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		$settings = array_intersect_key( $config, SectionImageRecommendationTaskType::DEFAULT_SETTINGS );
		$taskType = new SectionImageRecommendationTaskType(
			$taskTypeId,
			$config['group'],
			$settings
		);
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	/** @inheritDoc */
	public function getId(): string {
		return self::ID;
	}

	/** @inheritDoc */
	public function getRecommendationProvider(): ImageRecommendationProvider {
		return $this->recommendationProvider;
	}

	/** @inheritDoc */
	public function getSearchTerm( TaskType $taskType ): string {
		if ( $taskType->getHandlerId() !== self::ID ) {
			throw new InvalidArgumentException( '$taskType must be a section image recommendation task type' );
		}
		return parent::getSearchTerm( $taskType ) . 'hasrecommendation:image_section';
	}

	/**
	 * @inheritDoc
	 * @return AddSectionImageSubmissionHandler
	 */
	public function getSubmissionHandler(): SubmissionHandler {
		return $this->submissionHandler;
	}

	public function getSubmitDataFormatMessage( TaskType $taskType, MessageLocalizer $localizer ): MessageSpecifier {
		return $localizer->msg(
			'apihelp-growthexperiments-structured-task-submit-data-format-section-image-recommendation',
		);
	}
}
