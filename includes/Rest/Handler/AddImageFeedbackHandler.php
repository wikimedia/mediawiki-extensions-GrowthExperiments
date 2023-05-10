<?php

namespace GrowthExperiments\Rest\Handler;

use ApiMessage;
use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\Util;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\JsonBodyValidator;
use MediaWiki\Rest\Validator\UnsupportedContentTypeBodyValidator;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
use Status;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Accept image recommendation feedback. Basically just a wrapper for AddImageSubmissionHandler.
 */
class AddImageFeedbackHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;

	private TitleFactory $titleFactory;
	private RevisionLookup $revisionLookup;
	private ConfigurationLoader $configurationLoader;
	private AddImageSubmissionHandler $addImageSubmissionHandler;

	/**
	 * @param TitleFactory $titleFactory
	 * @param RevisionLookup $revisionLookup
	 * @param ConfigurationLoader $configurationLoader
	 * @param AddImageSubmissionHandler $addImageSubmissionHandler
	 */
	public function __construct(
		TitleFactory $titleFactory,
		RevisionLookup $revisionLookup,
		ConfigurationLoader $configurationLoader,
		AddImageSubmissionHandler $addImageSubmissionHandler
	) {
		$this->titleFactory = $titleFactory;
		$this->revisionLookup = $revisionLookup;
		$this->configurationLoader = $configurationLoader;
		$this->addImageSubmissionHandler = $addImageSubmissionHandler;
	}

	public function run() {
		$user = $this->getAuthority()->getUser();
		$title = $this->titleFactory->newFromLinkTarget( $this->getValidatedParams()['title'] );
		$data = $this->getValidatedBody();
		$editRevId = $data['editRevId'];

		if ( !$user->isRegistered() ) {
			throw $this->makeException( 'rest-permission-denied-anon', [], 401 );
		}
		if ( $data['accepted'] && !$editRevId ) {
			throw $this->makeException( 'growthexperiments-addimage-feedback-accepted-editrevid' );
		} elseif ( !$data['accepted'] && $editRevId ) {
			throw $this->makeException( 'growthexperiments-addimage-feedback-rejected-editrevid' );
		}
		if ( $data['accepted'] && ( $data['caption'] ?? null ) === null ) {
			throw $this->makeException( 'growthexperiments-addimage-feedback-accepted-caption' );
		}

		$editRev = null;
		if ( $editRevId !== null ) {
			$editRev = $this->revisionLookup->getRevisionById( $editRevId );
			if ( !$editRev ) {
				throw $this->makeException( 'growthexperiments-addimage-feedback-revid-nonexistent', [ $editRev ] );
			} elseif ( $editRev->getPageId() !== $title->getArticleID() ) {
				throw $this->makeException(
					'growthexperiments-addimage-feedback-invalid-revid-wrong-page',
					[ $editRevId, $title->getPrefixedText() ]
				);
			}
		}
		// The handler doesn't use the base revid so make things simple and just fake it.
		if ( $editRev ) {
			$baseRev = $this->revisionLookup->getPreviousRevision( $editRev );
			$baseRevId = $baseRev ? $baseRev->getId() : null;
		} else {
			$baseRevId = $title->getLatestRevID();
		}

		// TODO support section images
		$taskType = $this->configurationLoader->getTaskTypes()['image-recommendation'] ?? null;
		if ( !( $taskType instanceof ImageRecommendationTaskType ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'growthexperiments-newcomertasks-invalid-tasktype', [ 'image-recommendation' ] )
			);
		}

		$status = $this->addImageSubmissionHandler->validate(
			$taskType, $title->toPageIdentity(), $user, $baseRevId, $data
		);
		if ( $status->isGood() ) {
			$status->merge( $this->addImageSubmissionHandler->handle(
				$taskType, $title->toPageIdentity(), $user, $baseRevId, $editRevId, $data
			), true );
		}
		if ( !$status->isGood() ) {
			Util::logStatus( $status );
			// There isn't any good way to convert a Message into a MessageValue.
			$errorKey = ( new ApiMessage( Status::wrap( $status )->getMessage() ) )->getApiCode();
			throw new HttpException(
				Status::wrap( $status )->getMessage( false, false, 'en' )->text(),
				$status->isOK() ? 400 : 500,
				[ 'errorKey' => $errorKey ]
			);
		}

		return [ 'success' => true ] + $status->getValue();
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'title' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_REQUIRED => true,
				TitleDef::PARAM_RETURN_OBJECT => true,
				TitleDef::PARAM_MUST_EXIST => true,
			],
		];
	}

	/** @inheritDoc */
	public function getBodyValidator( $contentType ) {
		if ( $contentType === 'application/json' ) {
			return new JsonBodyValidator(
				[
					'editRevId' => [
						ParamValidator::PARAM_TYPE => 'integer',
						ParamValidator::PARAM_REQUIRED => false,
					],
					'filename' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'accepted' => [
						ParamValidator::PARAM_TYPE => 'boolean',
						ParamValidator::PARAM_REQUIRED => true,
					],
					'reasons' => [
						ParamValidator::PARAM_TYPE => AddImageSubmissionHandler::REJECTION_REASONS,
						ParamValidator::PARAM_ISMULTI => true,
						ParamValidator::PARAM_REQUIRED => false,
					],
					'caption' => [
						ParamValidator::PARAM_TYPE => 'string',
						ParamValidator::PARAM_REQUIRED => false,
					],
				] + $this->getTokenParamDefinition()
			);
		}
		return new UnsupportedContentTypeBodyValidator( $contentType );
	}

	/**
	 * @inheritDoc
	 */
	public function validate( Validator $restValidator ) {
		parent::validate( $restValidator );
		$this->validateToken();
	}

	private function makeException( string $messageKey, array $params = [], int $errorCode = 400 ) {
		return new LocalizedHttpException( new MessageValue( $messageKey, $params ), $errorCode );
	}

}
