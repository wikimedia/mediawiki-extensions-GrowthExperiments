<?php

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AddLink\AddLinkSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use GrowthExperiments\NewcomerTasks\RecommendationSubmissionHandler;
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

	/** @var AddLinkSubmissionHandler */
	private $submissionHandler;

	/**
	 * @param ConfigurationValidator $configurationValidator
	 * @param RecommendationProvider $recommendationProvider
	 * @param AddLinkSubmissionHandler $submissionHandler
	 */
	public function __construct(
		ConfigurationValidator $configurationValidator,
		RecommendationProvider $recommendationProvider,
		AddLinkSubmissionHandler $submissionHandler
	) {
		Assert::parameterType( LinkRecommendationProvider::class, $recommendationProvider,
			'$recommendationProvider' );
		parent::__construct( $configurationValidator );
		$this->recommendationProvider = $recommendationProvider;
		$this->submissionHandler = $submissionHandler;
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

	/**
	 * @inheritDoc
	 * @return AddLinkSubmissionHandler
	 */
	public function getSubmissionHandler(): RecommendationSubmissionHandler {
		return $this->submissionHandler;
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
