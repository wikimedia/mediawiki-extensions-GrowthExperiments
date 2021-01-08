<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TaskTypeHandlerRegistry;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskTypeHandler;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Linker\LinkTarget;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use Message;
use StatusValue;
use TitleFactory;
use TitleValue;

/**
 * Load configuration from a local or remote .json wiki page.
 * For syntax see
 * https://cs.wikipedia.org/wiki/MediaWiki:NewcomerTasks.json
 * https://cs.wikipedia.org/wiki/MediaWiki:NewcomerTopics.json
 * https://www.mediawiki.org/wiki/MediaWiki:NewcomerTopicsOres.json
 */
class PageConfigurationLoader implements ConfigurationLoader, PageSaveCompleteHook {

	use ConfigurationLoaderTrait;

	/** @var string Use the configuration for OresBasedTopic topics. */
	public const CONFIGURATION_TYPE_ORES = 'ores';
	/** @var string Use the configuration for MorelikeBasedTopic topics. */
	public const CONFIGURATION_TYPE_MORELIKE = 'morelike';

	private const VALID_TOPIC_TYPES = [
		self::CONFIGURATION_TYPE_ORES,
		self::CONFIGURATION_TYPE_MORELIKE,
	];

	/** @var TitleFactory */
	private $titleFactory;

	/** @var PageLoader */
	private $pageLoader;

	/** @var TaskTypeHandlerRegistry */
	private $taskTypeHandlerRegistry;

	/** @var ConfigurationValidator */
	private $configurationValidator;

	/** @var LinkTarget */
	private $taskConfigurationPage;

	/** @var LinkTarget|null */
	private $topicConfigurationPage;

	/** @var TaskType[]|StatusValue|null Cached task type set (or an error). */
	private $taskTypes;

	/** @var Topic[]|StatusValue|null Cached topic set (or an error). */
	private $topics;

	/** @var LinkTarget[]|StatusValue|null Cached template blacklist (or an error). */
	private $templateBlacklist;

	/**
	 * @var string One of the PageConfigurationLoader::CONFIGURATION_TYPE constants.
	 */
	private $topicType;

	/**
	 * @param TitleFactory $titleFactory
	 * @param PageLoader $pageLoader
	 * @param ConfigurationValidator $configurationValidator
	 * @param TaskTypeHandlerRegistry $taskTypeHandlerRegistry
	 * @param string|LinkTarget $taskConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki).
	 * @param string|LinkTarget|null $topicConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki). Can be omitted, in which case topic matching will be disabled.
	 * @param string $topicType One of the PageConfigurationLoader::CONFIGURATION_TYPE constants.
	 */
	public function __construct(
		TitleFactory $titleFactory,
		PageLoader $pageLoader,
		ConfigurationValidator $configurationValidator,
		TaskTypeHandlerRegistry $taskTypeHandlerRegistry,
		$taskConfigurationPage,
		$topicConfigurationPage,
		string $topicType
	) {
		$this->titleFactory = $titleFactory;
		$this->pageLoader = $pageLoader;
		$this->configurationValidator = $configurationValidator;
		$this->taskTypeHandlerRegistry = $taskTypeHandlerRegistry;
		$this->taskConfigurationPage = $taskConfigurationPage;
		$this->topicConfigurationPage = $topicConfigurationPage;
		$this->topicType = $topicType;

		if ( !in_array( $this->topicType, self::VALID_TOPIC_TYPES, true ) ) {
			throw new InvalidArgumentException( 'Invalid topic type ' . $this->topicType );
		}
	}

	/** @inheritDoc */
	public function loadTaskTypes() {
		if ( $this->taskTypes !== null ) {
			return $this->taskTypes;
		}

		$config = $this->pageLoader->load( $this->makeTitle( $this->taskConfigurationPage ) );
		if ( $config instanceof StatusValue ) {
			$taskTypes = $config;
		} else {
			$taskTypes = $this->parseTaskTypesFromConfig( $config );
		}

		$this->taskTypes = $taskTypes;
		return $taskTypes;
	}

	/** @inheritDoc */
	public function loadTopics() {
		if ( !$this->topicConfigurationPage ) {
			return [];
		} elseif ( $this->topics !== null ) {
			return $this->topics;
		}

		$config = $this->pageLoader->load( $this->makeTitle( $this->topicConfigurationPage ) );
		if ( $config instanceof StatusValue ) {
			$topics = $config;
		} else {
			// @phan-suppress-next-line SecurityCheck-DoubleEscaped
			$topics = $this->parseTopicsFromConfig( $config );
		}

		$this->topics = $topics;
		return $topics;
	}

	/** @inheritDoc */
	public function loadTemplateBlacklist() {
		if ( $this->templateBlacklist !== null ) {
			return $this->templateBlacklist;
		}

		$config = $this->pageLoader->load( $this->makeTitle( $this->taskConfigurationPage ) );
		if ( $config instanceof StatusValue ) {
			$templateBlacklist = $config;
		} else {
			$templateBlacklist = $this->parseTemplateBlacklistFromConfig( $config );
		}

		$this->templateBlacklist = $templateBlacklist;
		return $templateBlacklist;
	}

	/**
	 * @param string|LinkTarget|null $target
	 * @return LinkTarget|null
	 */
	private function makeTitle( $target ) {
		if ( is_string( $target ) ) {
			$target = $this->titleFactory->newFromText( $target );
		}
		if ( $target && !$target->isExternal() && !$target->inNamespace( NS_MEDIAWIKI ) ) {
			throw new LogicException( 'Configuration page not in NS_MEDIAWIKI: '
				. $target->getPrefixedDBkey() );
		}
		return $target;
	}

	/**
	 * Like loadTaskTypes() but without caching.
	 * @param mixed $config A JSON value.
	 * @return TaskType[]|StatusValue
	 */
	private function parseTaskTypesFromConfig( $config ) {
		$status = StatusValue::newGood();
		$taskTypes = [];
		if ( !is_array( $config ) || array_filter( $config, 'is_array' ) !== $config ) {
			return StatusValue::newFatal(
				'growthexperiments-homepage-suggestededits-config-wrongstructure' );
		}
		foreach ( $config as $taskTypeId => $taskTypeData ) {
			// Fall back to legacy handler if not specified.
			$handlerId = $taskTypeData['type'] ?? TemplateBasedTaskTypeHandler::ID;
			if ( !$this->taskTypeHandlerRegistry->has( $handlerId ) ) {
				$status->fatal( 'growthexperiments-homepage-suggestededits-config-invalidhandlerid',
					$taskTypeId, $handlerId, Message::listParam(
						$this->taskTypeHandlerRegistry->getKnownIds(), 'comma' ) );
				continue;
			}
			$taskTypeHandler = $this->taskTypeHandlerRegistry->get( $handlerId );
			$status->merge( $taskTypeHandler->validateTaskTypeConfiguration( $taskTypeId, $taskTypeData ) );

			if ( $status->isGood() ) {
				$taskType = $taskTypeHandler->createTaskType( $taskTypeId, $taskTypeData );
				$taskTypes[] = $taskType;
				$status->merge( $taskTypeHandler->validateTaskTypeObject( $taskType ) );
			}
		}
		return $status->isGood() ? $taskTypes : $status;
	}

	/**
	 * Like loadTopics() but without caching.
	 * @param mixed $config A JSON value.
	 * @return TaskType[]|StatusValue
	 */
	private function parseTopicsFromConfig( $config ) {
		$status = StatusValue::newGood();
		$topics = [];
		if ( !is_array( $config ) || array_filter( $config, 'is_array' ) !== $config ) {
			return StatusValue::newFatal(
				'growthexperiments-homepage-suggestededits-config-wrongstructure' );
		}

		$groups = [];
		if ( $this->topicType === self::CONFIGURATION_TYPE_ORES ) {
			if ( !isset( $config['topics'] ) || !isset( $config['groups'] ) ) {
				return StatusValue::newFatal(
					'growthexperiments-homepage-suggestededits-config-wrongstructure' );
			}
			$groups = $config['groups'];
			$config = $config['topics'];
		}

		foreach ( $config as $topicId => $topicConfiguration ) {
			$status->merge( $this->configurationValidator->validateIdentifier( $topicId ) );
			$requiredFields = [
				self::CONFIGURATION_TYPE_ORES => [ 'group', 'oresTopics' ],
				self::CONFIGURATION_TYPE_MORELIKE => [ 'label', 'titles' ],
			][$this->topicType];
			foreach ( $requiredFields as $field ) {
				if ( !isset( $topicConfiguration[$field] ) ) {
					$status->fatal( 'growthexperiments-homepage-suggestededits-config-missingfield',
						'titles', $topicId );
				}
			}

			if ( !$status->isGood() ) {
				// don't try to load if the config data format was invalid
				continue;
			}

			if ( $this->topicType === self::CONFIGURATION_TYPE_ORES ) {
				'@phan-var array{group:string,oresTopics:string[]} $topicConfiguration';
				$oresTopics = [];
				foreach ( $topicConfiguration['oresTopics'] as $oresTopic ) {
					$oresTopics[] = (string)$oresTopic;
				}
				$topic = new OresBasedTopic( $topicId, $topicConfiguration['group'], $oresTopics );
				$status->merge( $this->configurationValidator->validateTopicMessages( $topic ) );
			} elseif ( $this->topicType === self::CONFIGURATION_TYPE_MORELIKE ) {
				'@phan-var array{label:string,titles:string[]} $topicConfiguration';
				$linkTargets = [];
				foreach ( $topicConfiguration['titles'] as $title ) {
					$linkTargets[] = new TitleValue( NS_MAIN, $title );
				}
				$topic = new MorelikeBasedTopic( $topicId, $linkTargets );
				$topic->setName( $topicConfiguration['label'] );
			} else {
				throw new LogicException( 'Impossible but this makes phan happy.' );
			}
			$topics[] = $topic;
		}

		if ( $this->topicType === self::CONFIGURATION_TYPE_ORES && $status->isGood() ) {
			$this->configurationValidator->sortTopics( $topics, $groups );
		}

		return $status->isGood() ? $topics : $status;
	}

	/**
	 * Like loadTemplateBlacklist() but without caching.
	 * @param array $config
	 * @return LinkTarget[]|StatusValue
	 */
	private function parseTemplateBlacklistFromConfig( array $config ) {
		// TODO: add templates to the wiki page and implement parsing them here.
		return [];
	}

	/**
	 * Invalidate configuration cache when needed.
	 * {@inheritDoc}
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		$title = $wikiPage->getTitle();
		if ( !$title || !$title->inNamespace( NS_MEDIAWIKI ) ) {
			return;
		}

		$taskConfigurationTitle = $this->makeTitle( $this->taskConfigurationPage );
		$topicConfigurationTitle = $this->makeTitle( $this->topicConfigurationPage );
		if ( $title->equals( $taskConfigurationTitle ) ) {
			$this->pageLoader->invalidate( $taskConfigurationTitle );
		} elseif ( $topicConfigurationTitle && $title->equals( $topicConfigurationTitle ) ) {
			$this->pageLoader->invalidate( $topicConfigurationTitle );
		}
	}

}
