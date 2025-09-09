<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\RecommendationProvider;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use GrowthExperiments\NewcomerTasks\SubpageReviseToneRecommendationProvider;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use LogicException;
use MediaWiki\Title\TitleParser;
use MessageLocalizer;
use Wikimedia\Message\MessageSpecifier;

class ReviseToneTaskTypeHandler extends StructuredTaskTypeHandler {

	private const ID = 'revise-tone';

	public const TASK_TYPE_ID = 'revise-tone';

	public const CHANGE_TAG = 'newcomer task revise tone';

	public function __construct(
		ConfigurationValidator $configurationValidator,
		TitleParser $titleParser,
		private readonly SubpageReviseToneRecommendationProvider $recommendationProvider,
	) {
		parent::__construct( $configurationValidator, $titleParser );
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return self::ID;
	}

	/**
	 * @inheritDoc
	 */
	public function getSubmissionHandler(): SubmissionHandler {
		// TODO: figure out if we need to do anything special for this task
		return new TemplateBasedTaskSubmissionHandler();
	}

	public function getSearchTerm( TaskType $taskType ): string {
		$searchTerm = parent::getSearchTerm( $taskType );
		// TODO: this should maybe be configurable for the beta, and replaced with hasrecommendation:tone
		//       once we have real recommendations
		$searchTerm .= 'hastemplate:peacock_inline ';
		return $searchTerm;
	}

	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		$taskType = new ReviseToneTaskType( $taskTypeId, $config['group'] );
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	public function getRecommendationProvider(): RecommendationProvider {
		return $this->recommendationProvider;
	}

	public function getSubmitDataFormatMessage( TaskType $taskType, MessageLocalizer $localizer ): MessageSpecifier {
		return $localizer->msg(
			'apihelp-growthexperiments-structured-task-submit-data-format-revise-tone',
		);
	}

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

}
