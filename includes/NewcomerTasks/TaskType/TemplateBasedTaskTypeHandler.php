<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskType;

use GrowthExperiments\Config\Schemas\SuggestedEditsSchema;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationValidator;
use GrowthExperiments\NewcomerTasks\SubmissionHandler;
use GrowthExperiments\NewcomerTasks\TemplateBasedTaskSubmissionHandler;
use InvalidArgumentException;
use MediaWiki\Title\TitleParser;
use StatusValue;

/**
 * A handler for task types that represent an article with a certain maintenance template on it.
 */
class TemplateBasedTaskTypeHandler extends TaskTypeHandler {

	public const ID = 'template-based';

	public const NEWCOMER_TASK_COPYEDIT_TAG = 'newcomer task copyedit';
	public const NEWCOMER_TASK_REFERENCES_TAG = 'newcomer task references';
	public const NEWCOMER_TASK_UPDATE_TAG = 'newcomer task update';
	public const NEWCOMER_TASK_EXPAND_TAG = 'newcomer task expand';
	public const NEWCOMER_TASK_LINKS_TAG = 'newcomer task links';

	public const NEWCOMER_TASK_TEMPLATE_BASED_ALL_CHANGE_TAGS = [
		self::NEWCOMER_TASK_COPYEDIT_TAG,
		self::NEWCOMER_TASK_REFERENCES_TAG,
		self::NEWCOMER_TASK_UPDATE_TAG,
		self::NEWCOMER_TASK_EXPAND_TAG,
		self::NEWCOMER_TASK_LINKS_TAG,
	];

	private readonly TitleParser $titleParser;

	private readonly TemplateBasedTaskSubmissionHandler $submissionHandler;

	public function __construct(
		ConfigurationValidator $configurationValidator,
		TemplateBasedTaskSubmissionHandler $submissionHandler,
		TitleParser $titleParser
	) {
		parent::__construct( $configurationValidator, $titleParser );
		$this->titleParser = $titleParser;
		$this->submissionHandler = $submissionHandler;
	}

	/** @inheritDoc */
	public function getId(): string {
		return self::ID;
	}

	/** @inheritDoc */
	public function validateTaskTypeConfiguration( string $taskTypeId, array $config ): StatusValue {
		$status = parent::validateTaskTypeConfiguration( $taskTypeId, $config );

		$status->merge( $this->configurationValidator->validateArrayMaxSize(
			SuggestedEditsSchema::MAX_INFOBOX_TEMPLATES, $config['templates'],
			$taskTypeId, 'templates' ) );
		foreach ( $config['templates'] as $template ) {
			$this->validateTemplate( $template, $taskTypeId, $status );
		}
		return $status;
	}

	/** @inheritDoc */
	public function createTaskType( string $taskTypeId, array $config ): TaskType {
		'@phan-var array{group:string,templates:string[]} $config';
		$templates = [];
		foreach ( $config['templates'] as $template ) {
			$templates[] = $this->titleParser->parseTitle( $template, NS_TEMPLATE );
		}
		$taskType = new TemplateBasedTaskType(
			$taskTypeId,
			$config['group'],
			[ 'learnMoreLink' => $config['learnmore'] ?? null ],
			$templates,
			$this->parseExcludedTemplates( $config ),
			$this->parseExcludedCategories( $config )
		);
		$taskType->setHandlerId( $this->getId() );
		return $taskType;
	}

	/** @inheritDoc */
	public function getSearchTerm( TaskType $taskType ): string {
		if ( !$taskType instanceof TemplateBasedTaskType ) {
			throw new InvalidArgumentException( '$taskType must be a TemplateBasedTaskType' );
		}
		return parent::getSearchTerm( $taskType ) .
			'hastemplate:' . Util::escapeSearchTitleList( $taskType->getTemplates() );
	}

	public function getSubmissionHandler(): SubmissionHandler {
		return $this->submissionHandler;
	}

	/** @inheritDoc */
	public function getChangeTags( ?string $taskType = null ): array {
		if ( !$taskType ) {
			return self::NEWCOMER_TASK_TEMPLATE_BASED_ALL_CHANGE_TAGS;
		}
		$taskTypeSpecificTag = match ( $taskType ) {
			'copyedit' => self::NEWCOMER_TASK_COPYEDIT_TAG,
			'references' => self::NEWCOMER_TASK_REFERENCES_TAG,
			'update' => self::NEWCOMER_TASK_UPDATE_TAG,
			'expand' => self::NEWCOMER_TASK_EXPAND_TAG,
			'links' => self::NEWCOMER_TASK_LINKS_TAG,
			default => throw new InvalidArgumentException( "$taskType is not valid." ),
		};
		return [ self::NEWCOMER_TASK_TAG, $taskTypeSpecificTag ];
	}

	/** @inheritDoc */
	public function getTaskTypeIdByChangeTagName( string $changeTagName ): ?string {
		return match ( $changeTagName ) {
			self::NEWCOMER_TASK_COPYEDIT_TAG => 'copyedit',
			self::NEWCOMER_TASK_REFERENCES_TAG => 'references',
			self::NEWCOMER_TASK_UPDATE_TAG => 'update',
			self::NEWCOMER_TASK_EXPAND_TAG => 'expand',
			self::NEWCOMER_TASK_LINKS_TAG => 'links',
			default => throw new InvalidArgumentException( "$changeTagName is not valid" ),
		};
	}

}
