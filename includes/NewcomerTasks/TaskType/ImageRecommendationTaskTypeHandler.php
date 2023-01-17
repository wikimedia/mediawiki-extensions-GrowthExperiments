<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use InvalidArgumentException;
use Message;
use MessageLocalizer;
use MessageSpecifier;
use StatusValue;
use TitleParser;
use Wikimedia\Assert\Assert;

class ImageRecommendationTaskTypeHandler extends StructuredTaskTypeHandler {

	public const ID = 'image-recommendation';

	public const TASK_TYPE_ID = 'image-recommendation';

	public const CHANGE_TAG = 'newcomer task image suggestion';

	/** The tag prefix used for CirrusSearch\Wikimedia\WeightedTags. */
	public const WEIGHTED_TAG_PREFIX = 'recommendation.image';

	/** @var ImageRecommendationProvider */
	private $recommendationProvider;

	/** @var AddImageSubmissionHandler */
	private $submissionHandler;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param TitleParser $titleParser
	 * @param RecommendationProvider $recommendationProvider
	 * @param AddImageSubmissionHandler $submissionHandler
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		TitleParser $titleParser,
		RecommendationProvider $recommendationProvider,
		AddImageSubmissionHandler $submissionHandler
	) {
		parent::__construct( $configurationValidator, $titleParser );
		Assert::parameterType( ImageRecommendationProvider::class, $recommendationProvider,
			'$recommendationProvider' );
		$this->recommendationProvider = $recommendationProvider;
		$this->submissionHandler = $submissionHandler;
	}

	/** @inheritDoc */
	public function getId(): string {
		return self::ID;
	}

	/**
	 * @inheritDoc
	 * @return ImageRecommendationProvider
	 */
	public function getRecommendationProvider(): RecommendationProvider {
		return $this->recommendationProvider;
	}

	/**
	 * @inheritDoc
	 * @return AddImageSubmissionHandler
	 */
	public function getSubmissionHandler(): SubmissionHandler {
		return $this->submissionHandler;
	}

	/** @inheritDoc */
	public function createTaskType( string $taskTypeId, array $config ): TaskType {
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
	public function validateTaskTypeConfiguration( string $taskTypeId, array $config ): StatusValue {
		$status = parent::validateTaskTypeConfiguration( $taskTypeId, $config );
		if ( !$status->isOK() ) {
			return $status;
		}
		foreach ( [
			ImageRecommendationTaskType::FIELD_MAX_TASKS_PER_DAY,
			ImageRecommendationTaskType::FIELD_MINIMUM_CAPTION_CHARACTER_LENGTH,
		  ] as $field ) {
			if ( array_key_exists( $field, $config ) ) {
				$status->merge( $this->configurationValidator->validateInteger(
					$config, $field, $taskTypeId, 1 ) );
			}
		}
		return $status;
	}

	/** @inheritDoc */
	public function getSearchTerm( TaskType $taskType ): string {
		if ( $taskType->getHandlerId() !== self::ID ) {
			throw new InvalidArgumentException( '$taskType must be an image recommendation task type' );
		}
		return parent::getSearchTerm( $taskType ) . 'hasrecommendation:image' . ' ' . '-hastemplatecollection:infobox';
	}

	/** @inheritDoc */
	public function getChangeTags( ?string $taskType = null ): array {
		return [ TaskTypeHandler::NEWCOMER_TASK_TAG, self::CHANGE_TAG ];
	}

	/** @inheritDoc */
	public function getSubmitDataFormatMessage(
		TaskType $taskType,
		MessageLocalizer $localizer
	): MessageSpecifier {
		if ( !( $taskType instanceof ImageRecommendationTaskType ) ) {
			throw new \LogicException( 'impossible' );
		}
		$wrappedReasons = array_map(
			fn( $reason ) => "<kbd>$reason</kbd>",
			AddImageSubmissionHandler::REJECTION_REASONS
		);
		return $localizer->msg(
			'apihelp-growthexperiments-structured-task-submit-data-format-image-recommendation',
			Message::listParam( $wrappedReasons, 'comma' ),
			Message::numParam( $taskType->getMinimumCaptionCharacterLength() )
		);
	}

}
