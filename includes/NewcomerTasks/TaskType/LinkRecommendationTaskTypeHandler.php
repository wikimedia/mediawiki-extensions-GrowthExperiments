<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use InvalidArgumentException;
use Wikimedia\Assert\Assert;

class LinkRecommendationTaskTypeHandler extends StructuredTaskTypeHandler {

	public const ID = 'link-recommendation';

	public const TASK_TYPE_ID = 'link-recommendation';

	public const CHANGE_TAG = 'newcomer task add link';

	/** The tag prefix used for CirrusSearch\Wikimedia\WeightedTags. */
	public const WEIGHTED_TAG_PREFIX = 'recommendation.link';

	/** @var LinkRecommendationProvider */
	private $recommendationProvider;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param RecommendationProvider $recommendationProvider
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		RecommendationProvider $recommendationProvider
	) {
		Assert::parameterType( LinkRecommendationProvider::class, $recommendationProvider,
			'$recommendationProvider' );
		parent::__construct( $configurationValidator );
		$this->recommendationProvider = $recommendationProvider;
	}

	/** @inheritDoc */
	public function getId(): string {
		return self::ID;
	}

	/**
	 * @inheritDoc
	 * @return LinkRecommendationProvider
	 */
	public function getRecommendationProvider(): RecommendationProvider {
		return $this->recommendationProvider;
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
