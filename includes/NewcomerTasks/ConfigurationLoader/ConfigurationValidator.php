<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MediaWiki\Collation\CollationFactory;
use MediaWiki\Message\Message;
use MessageLocalizer;
use StatusValue;

/**
 * Helper class for validating task type / topic / etc. configuration.
 */
class ConfigurationValidator {

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var CollationFactory */
	private $collationFactory;

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param CollationFactory $collationFactory
	 */
	public function __construct(
		MessageLocalizer $messageLocalizer,
		CollationFactory $collationFactory
	) {
		$this->messageLocalizer = $messageLocalizer;
		$this->collationFactory = $collationFactory;
	}

	/**
	 * Inject the message localizer.
	 * @param MessageLocalizer $messageLocalizer
	 * @internal To be used by ResourceLoader callbacks only.
	 * @note This is an ugly hack. Normal requests use the global RequestContext as a localizer,
	 *   which is a bit of a kitchen sink, but conceptually can be thought of as a service.
	 *   ResourceLoader provides the ResourceLoaderContext, which is not global and can only be
	 *   obtained by code directly invoked by ResourceLoader. The ConfigurationLoader depends
	 *   on whichever of the two is available, so the localizer cannot be injected in the service
	 *   wiring file, and a factory would not make sense conceptually (there should never be
	 *   multiple configuration loaders). So we provide this method so that the ResourceLoader
	 *   callback can finish the dependency injection.
	 */
	public function setMessageLocalizer( MessageLocalizer $messageLocalizer ): void {
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Verify that a field is an integer, and optionally within some bounds. The field doesn't have
	 * to exist.
	 * @param array $config Configuration
	 * @param string $field Configuration field name
	 * @param string $taskTypeId Task type ID, for better error reporting
	 * @param int|null $min Minimum value
	 * @return StatusValue
	 */
	public function validateInteger(
		array $config, string $field, string $taskTypeId, ?int $min = null
	) {
		if ( !array_key_exists( $field, $config ) ) {
			return StatusValue::newGood();
		}
		$value = $config[$field];
		if ( !is_int( $value ) ) {
			return StatusValue::newFatal( 'growthexperiments-homepage-suggestededits-config-notinteger',
				$field, $taskTypeId );
		} elseif ( $min !== null && $value < $min ) {
			return StatusValue::newFatal( 'growthexperiments-homepage-suggestededits-config-toosmall',
				$field, $taskTypeId, $min );
		}
		return StatusValue::newGood();
	}

	/**
	 * Verify that an array doesn't exceed an allowed maximum size.
	 *
	 * @param int $maxSize
	 * @param array $config Configuration
	 * @param string $taskTypeId Task type ID, for better error reporting.
	 * @param string $field Configuration field name, for better error reporting.
	 * @return StatusValue
	 */
	public function validateArrayMaxSize( int $maxSize, array $config, string $taskTypeId, string $field ) {
		$status = StatusValue::newGood();
		if ( count( $config ) > $maxSize ) {
			$status->fatal( 'growthexperiments-homepage-suggestededits-config-arraymaxsize',
				$taskTypeId, $field, Message::numParam( $maxSize ) );
		}
		return $status;
	}

	/**
	 * For a given list of messages, verifies that they all exist.
	 * @param Message[] $messages
	 * @param string $field Field name where the missing message was defined (e.g. ID of the task).
	 * @return StatusValue
	 */
	public function validateMessages( array $messages, string $field ) {
		$status = StatusValue::newGood();
		foreach ( $messages as $msg ) {
			if ( !$msg->exists() ) {
				$status->fatal( 'growthexperiments-homepage-suggestededits-config-missingmessage',
					$msg->getKey(), $field );
			}
		}
		return $status;
	}

	/**
	 * Ensure that all messages used by the task type exist.
	 * @param TaskType $taskType
	 * @return StatusValue
	 */
	public function validateTaskMessages( TaskType $taskType ) {
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
	public function validateTopicMessages( Topic $topic ) {
		$messages = [ $topic->getName( $this->messageLocalizer ) ];
		if ( $topic->getGroupId() ) {
			$messages[] = $topic->getGroupName( $this->messageLocalizer );
		}
		return $this->validateMessages( $messages, $topic->getId() );
	}

	/**
	 * Sorts topics in-place, based on the group configuration and alphabetically within that.
	 * @param Topic[] &$topics
	 * @param string[] $groups
	 */
	public function sortTopics( array &$topics, $groups ) {
		if ( !$topics ) {
			return;
		}

		$collation = $this->collationFactory->getCategoryCollation();

		usort( $topics, function ( Topic $left, Topic $right ) use ( $groups, $collation ) {
			$leftGroup = $left->getGroupId();
			$rightGroup = $right->getGroupId();
			if ( $leftGroup !== $rightGroup ) {
				return array_search( $leftGroup, $groups, true ) - array_search( $rightGroup, $groups, true );
			}

			$leftSortKey = $collation->getSortKey(
				$left->getName( $this->messageLocalizer )->text() );
			$rightSortKey = $collation->getSortKey(
				$right->getName( $this->messageLocalizer )->text() );
			return strcmp( $leftSortKey, $rightSortKey );
		} );
	}

}
