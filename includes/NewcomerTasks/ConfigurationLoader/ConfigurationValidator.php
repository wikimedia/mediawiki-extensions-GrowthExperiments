<?php

namespace GrowthExperiments\NewcomerTasks\ConfigurationLoader;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Message\Message;
use MessageLocalizer;
use StatusValue;

/**
 * Helper class for validating task type / topic / etc. configuration.
 */
class ConfigurationValidator {

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/**
	 * @param MessageLocalizer $messageLocalizer
	 */
	public function __construct(
		MessageLocalizer $messageLocalizer
	) {
		$this->messageLocalizer = $messageLocalizer;
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
			$taskType->getTimeEstimate( $this->messageLocalizer ),
		], $taskType->getId() );
	}
}
