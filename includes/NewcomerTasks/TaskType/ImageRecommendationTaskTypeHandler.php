<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AddImage\ImageRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use InvalidArgumentException;
use Wikimedia\Assert\Assert;

class ImageRecommendationTaskTypeHandler extends StructuredTaskTypeHandler {

	public const ID = 'image-recommendation';

	public const TASK_TYPE_ID = 'image-recommendation';

	public const CHANGE_TAG = 'newcomer task image suggestion';

	/** The tag prefix used for CirrusSearch\Wikimedia\WeightedTags. */
	public const WEIGHTED_TAG_PREFIX = 'recommendation.image';

	/** @var ImageRecommendationProvider */
	private $recommendationProvider;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param RecommendationProvider $recommendationProvider
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		RecommendationProvider $recommendationProvider
	) {
		parent::__construct( $configurationValidator );
		Assert::parameterType( ImageRecommendationProvider::class, $recommendationProvider,
			'$recommendationProvider' );
		$this->recommendationProvider = $recommendationProvider;
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
