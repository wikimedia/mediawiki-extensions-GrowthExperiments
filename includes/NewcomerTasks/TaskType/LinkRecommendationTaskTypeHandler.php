<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\AddLink\AddLinkSubmissionHandler;
use GrowthExperiments\NewcomerTasks\AddLink\LinkRecommendationProvider;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Title\TitleParser;
use Wikimedia\Message\MessageSpecifier;

class LinkRecommendationTaskTypeHandler extends StructuredTaskTypeHandler {

	public const ID = 'link-recommendation';

	public const TASK_TYPE_ID = 'link-recommendation';

	public const CHANGE_TAG = 'newcomer task add link';

	/** The tag prefix used for CirrusSearch\Wikimedia\WeightedTags. */
	public const WEIGHTED_TAG_PREFIX = 'recommendation.link';

	public function __construct(
		ConfigurationValidator $configurationValidator,
		TitleParser $titleParser,
		private readonly LinkRecommendationProvider $recommendationProvider,
		private readonly AddLinkSubmissionHandler $submissionHandler
	) {
		parent::__construct( $configurationValidator, $titleParser );
	}

	/** @inheritDoc */
	public function getId(): string {
		return self::ID;
	}

	/**
	 * @inheritDoc
	 */
	public function getRecommendationProvider(): LinkRecommendationProvider {
		return $this->recommendationProvider;
	}

	/**
	 * @inheritDoc
	 */
	public function getSubmissionHandler(): AddLinkSubmissionHandler {
		return $this->submissionHandler;
	}

	private function parseMaximumEditsTaskIsAvailable( string $configValue ): ?int {
		if ( !$configValue || $configValue === 'no' ) {
			return null;
		}
		return (int)$configValue;
	}

	/** @inheritDoc */
	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		$extraData = [ 'learnMoreLink' => $config['learnmore'] ?? null ];
		// FIXME add settings validation
		$settings = array_intersect_key( $config, LinkRecommendationTaskType::DEFAULT_SETTINGS );
		$settings['maximumEditsTaskIsAvailable'] = $this->parseMaximumEditsTaskIsAvailable(
			$config['maximumEditsTaskIsAvailable']
		);

		$taskType = new LinkRecommendationTaskType(
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
			throw new InvalidArgumentException( '$taskType must be a link recommendation task type' );
		}
		return parent::getSearchTerm( $taskType ) . 'hasrecommendation:link';
	}

	/** @inheritDoc */
	public function getChangeTags( ?string $taskType = null ): array {
		return [ TaskTypeHandler::NEWCOMER_TASK_TAG, self::CHANGE_TAG ];
	}

	/** @inheritDoc */
	public function getTaskTypeIdByChangeTagName( string $changeTagName ): ?string {
		if ( $changeTagName !== self::CHANGE_TAG ) {
			throw new LogicException( "\"$changeTagName\" is not a valid change tag name for " . self::class );
		}
		return self::TASK_TYPE_ID;
	}

	/** @inheritDoc */
	public function getSubmitDataFormatMessage(
		TaskType $taskType,
		MessageLocalizer $localizer
	): MessageSpecifier {
		return $localizer->msg(
			'apihelp-growthexperiments-structured-task-submit-data-format-link-recommendation'
		);
	}

}
