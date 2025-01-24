<?php

namespace GrowthExperiments\Rest\Handler;

use GrowthExperiments\NewcomerTasks\NewcomerTasksChangeTagsManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handle POST requests to /growthexperiments/v0/newcomertasks/complete
 *
 * Used for applying relevant change tags to revisions.
 */
class NewcomerTaskCompleteHandler extends SimpleHandler {

	private NewcomerTasksChangeTagsManager $newcomerTasksChangeTagsManager;

	public function __construct( NewcomerTasksChangeTagsManager $newcomerTasksChangeTagsManager ) {
		$this->newcomerTasksChangeTagsManager = $newcomerTasksChangeTagsManager;
	}

	/**
	 * @return array
	 * @throws HttpException
	 */
	public function run(): array {
		$params = $this->getValidatedParams();

		$result = $this->newcomerTasksChangeTagsManager->apply(
			$params['taskTypeId'], $params['revId'], $this->getAuthority()->getUser()
		);
		if ( !$result->isGood() ) {
			// HACK: We know we're not merging status values, so we can
			// just use the first one.
			$error = current( $result->getErrors() );
			throw new HttpException( $error['message'], $this->getErrorCodeForMessage( $error['message'] ) );
		}
		return [ $result->getValue() ];
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'taskTypeId' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'revId' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	private function getErrorCodeForMessage( string $message ): int {
		if ( strpos( $message, 'Invalid task type ID' ) !== false ) {
			return 400;
		} elseif ( strpos( $message, ' is not a valid revision ID' ) !== false ) {
			return 400;
		} elseif ( strpos( $message, 'revision does not match logged-in user ID' ) !== false ) {
			return 400;
		} elseif ( strpos( $message, 'Revision already has newcomer task tag.' ) !== false ) {
			return 400;
		} elseif ( strpos( $message, 'Suggested edits are not enabled or activated for your user.' ) !== false ) {
			return 403;
		} elseif ( strpos( $message, 'You must be logged-in' ) !== false ) {
			return 403;
		}
		return 500;
	}

}
