<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use Collation;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\TaskType\TemplateBasedTaskType;
use GrowthExperiments\NewcomerTasks\Topic\MorelikeBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\OresBasedTopic;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Linker\LinkTarget;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Message;
use MessageLocalizer;
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
class PageConfigurationLoader implements ConfigurationLoader {

	/** @var string Use the configuration for OresBasedTopic topics. */
	public const CONFIGURATION_TYPE_ORES = 'ores';
	/** @var string Use the configuration for MorelikeBasedTopic topics. */
	public const CONFIGURATION_TYPE_MORELIKE = 'morelike';

	protected static $validTopicTypes = [
		self::CONFIGURATION_TYPE_ORES,
		self::CONFIGURATION_TYPE_MORELIKE,
	];

	/** @var TitleFactory */
	private $titleFactory;

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var PageLoader */
	private $pageLoader;

	/** @var Collation */
	private $collation;

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
	 * @param MessageLocalizer $messageLocalizer
	 * @param PageLoader $pageLoader
	 * @param Collation $collation
	 * @param string|LinkTarget $taskConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki).
	 * @param string|LinkTarget|null $topicConfigurationPage Wiki page to load task configuration from
	 *   (local or interwiki). Can be omitted, in which case topic matching will be disabled.
	 * @param string $topicType One of the PageConfigurationLoader::CONFIGURATION_TYPE constants.
	 */
	public function __construct(
		TitleFactory $titleFactory,
		MessageLocalizer $messageLocalizer,
		PageLoader $pageLoader,
		Collation $collation,
		$taskConfigurationPage,
		$topicConfigurationPage,
		string $topicType
	) {
		$this->titleFactory = $titleFactory;
		$this->messageLocalizer = $messageLocalizer;
		$this->pageLoader = $pageLoader;
		$this->collation = $collation;
		$this->taskConfigurationPage = $taskConfigurationPage;
		$this->topicConfigurationPage = $topicConfigurationPage;
		$this->topicType = $topicType;

		if ( !in_array( $this->topicType, self::$validTopicTypes, true ) ) {
			throw new InvalidArgumentException( 'Invalid topic type ' . $this->topicType );
		}
	}

	/** @inheritDoc */
	public function setMessageLocalizer( MessageLocalizer $messageLocalizer ): void {
		$this->messageLocalizer = $messageLocalizer;
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
	public function getTaskTypes(): array {
		$taskTypes = $this->loadTaskTypes();
		if ( $taskTypes instanceof StatusValue ) {
			return [];
		}
		return array_combine( array_map( function ( TaskType $taskType ) {
			return $taskType->getId();
		}, $taskTypes ), $taskTypes ) ?: [];
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
			$status->merge( $this->validateIdentifier( $taskTypeId ) );
			$requiredFields = [ 'group', 'templates' ];
			foreach ( $requiredFields as $field ) {
				if ( !isset( $taskTypeData[$field] ) ) {
					$status->fatal( 'growthexperiments-homepage-suggestededits-config-missingfield',
						$field, $taskTypeId );
				}
			}
			if ( isset( $taskTypeData['group'] ) &&
				!in_array( $taskTypeData['group'], TaskType::$difficultyClasses, true )
			) {
				$status->fatal( 'growthexperiments-homepage-suggestededits-config-wronggroup',
					$taskTypeData['group'], $taskTypeId );
			}

			if ( $status->isGood() ) {
				'@phan-var array{group:string,templates:string[]} $taskTypeData';
				$templates = array_map( function ( $template ) {
					return new TitleValue( NS_TEMPLATE, $template );
				}, $taskTypeData['templates'] );
				$taskType = new TemplateBasedTaskType(
					$taskTypeId,
					$taskTypeData['group'],
					[ 'learnMoreLink' => $taskTypeData['learnmore'] ?? null ],
					$templates
				);
				$status->merge( $this->validateTaskMessages( $taskType ) );
				$taskTypes[] = $taskType;
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
			$status->merge( $this->validateIdentifier( $topicId ) );
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
				$status->merge( $this->validateTopicMessages( $topic ) );
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
			$this->sortTopics( $topics, $groups );
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
	 * Validate a task or topic ID
	 * @param string $id
	 * @return StatusValue
	 */
	private function validateIdentifier( $id ) {
		return preg_match( '/^[a-z\d\-]+$/', $id )
			? StatusValue::newGood()
			: StatusValue::newFatal( 'growthexperiments-homepage-suggestededits-config-invalidid', $id );
	}

	/**
	 * Ensure that all messages used by the task type exist.
	 * @param TemplateBasedTaskType $taskType
	 * @return StatusValue
	 */
	private function validateTaskMessages( TemplateBasedTaskType $taskType ) {
		return $this->validateMessages( [
			$taskType->getName( $this->messageLocalizer ),
			$taskType->getDescription( $this->messageLocalizer ),
			$taskType->getShortDescription( $this->messageLocalizer ),
			$taskType->getTimeEstimate( $this->messageLocalizer )
		], $taskType->getId() );
	}

	/**
	 * Ensure that all messages used by the topic exist.
	 * @param Topic $topic
	 * @return StatusValue
	 */
	private function validateTopicMessages( Topic $topic ) {
		$messages = [ $topic->getName( $this->messageLocalizer ) ];
		if ( $topic->getGroupId() ) {
			$messages[] = $topic->getGroupName( $this->messageLocalizer );
		}
		return $this->validateMessages( $messages, $topic->getId() );
	}

	/**
	 * @param Message[] $messages
	 * @param string $field Field name where the missing message was defined (e.g. ID of the task).
	 * @return StatusValue
	 */
	private function validateMessages( array $messages, string $field ) {
		$status = StatusValue::newGood();
		foreach ( $messages as $msg ) {
			/** @var $msg Message */
			if ( !$msg->exists() ) {
				$status->fatal( 'growthexperiments-homepage-suggestededits-config-missingmessage',
					$msg->getKey(), $field );
			}
		}
		return $status;
	}

	/**
	 * Sorts topics in-place, based on the group configuration and alphabetically within that.
	 * @param Topic[] &$topics
	 * @param string[] $groups
	 */
	private function sortTopics( array &$topics, $groups ) {
		usort( $topics, function ( Topic $left, Topic $right ) use ( $groups ) {
			$leftGroup = $left->getGroupId();
			$rightGroup = $right->getGroupId();
			if ( $leftGroup !== $rightGroup ) {
				return array_search( $leftGroup, $groups, true ) - array_search( $rightGroup, $groups, true );
			}

			$leftSortKey = $this->collation->getSortKey(
				$left->getName( $this->messageLocalizer )->text() );
			$rightSortKey = $this->collation->getSortKey(
				$right->getName( $this->messageLocalizer )->text() );
			return strcmp( $leftSortKey, $rightSortKey );
		} );
	}

}
