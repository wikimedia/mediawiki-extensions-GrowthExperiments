<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use Collation;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use GrowthExperiments\NewcomerTasks\Topic\Topic;
use MalformedTitleException;
use Message;
use MessageLocalizer;
use StatusValue;
use TitleParser;

/**
 * Helper class for validating task type / topic / etc. configuration.
 */
class ConfigurationValidator {

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var Collation */
	private $collation;

	/** @var TitleParser */
	private $titleParser;

	/**
	 * @param MessageLocalizer $messageLocalizer
	 * @param Collation $collation
	 * @param TitleParser $titleParser
	 */
	public function __construct(
		MessageLocalizer $messageLocalizer,
		Collation $collation,
		TitleParser $titleParser
	) {
		$this->messageLocalizer = $messageLocalizer;
		$this->collation = $collation;
		$this->titleParser = $titleParser;
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
	 * Validate a task or topic ID
	 * @param string $id
	 * @return StatusValue
	 */
	public function validateIdentifier( $id ) {
		return preg_match( '/^[a-z\d\-]+$/', $id )
			? StatusValue::newGood()
			: StatusValue::newFatal( 'growthexperiments-homepage-suggestededits-config-invalidid', $id );
	}

	/**
	 * @param string $title
	 * @return StatusValue
	 */
	public function validateTitle( $title ) {
		if ( !is_string( $title ) ) {
			return StatusValue::newFatal( 'growthexperiments-homepage-suggestededits-config-invalidtitle',
				$title );
		}
		try {
			$this->titleParser->parseTitle( $title );
		} catch ( MalformedTitleException $e ) {
			return StatusValue::newFatal( 'growthexperiments-homepage-suggestededits-config-invalidtitle',
				$title );
		}
		return StatusValue::newGood();
	}

	/**
	 * Verify that a required field is present.
	 * @param string $field Configuration field name
	 * @param array $config Configuration
	 * @param string $taskTypeId Task type ID, for better error reporting
	 * @return StatusValue
	 */
	public function validateRequiredField( $field, $config, $taskTypeId ) {
		return isset( $config[$field] )
			? StatusValue::newGood()
			: StatusValue::newFatal( 'growthexperiments-homepage-suggestededits-config-missingfield',
				$field, $taskTypeId );
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
