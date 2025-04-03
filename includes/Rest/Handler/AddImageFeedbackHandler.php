<?php

namespace GrowthExperiments\Rest\Handler;

use Exception;
use GrowthExperiments\NewcomerTasks\AddImage\AddImageSubmissionHandler;
use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\ImageRecommendationTaskType;
use GrowthExperiments\Util;
use MediaWiki\Api\ApiMessage;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\TitleFactory;
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

	/** @inheritDoc */
	public function run() {
		$authority = $this->getAuthority();
		$user = $authority->getUser();
		$title = $this->titleFactory->newFromLinkTarget( $this->getValidatedParams()['title'] );
		$data = $this->getValidatedBody() ?? [];
		$editRevId = $data['editRevId'];

		if ( !$authority->isNamed() ) {
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
		$allTaskTypes = $this->configurationLoader->getTaskTypes()
			+ $this->configurationLoader->getDisabledTaskTypes();
		$taskType = $allTaskTypes['image-recommendation'] ?? null;
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
			// This assumes that there's no more than one message in the status object
			$msg = $status->getMessages()[0];
			$errorKey = ( new ApiMessage( $msg ) )->getApiCode();
			throw new LocalizedHttpException(
				MessageValue::newFromSpecifier( $msg ),
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
			]
		];
	}

	/**
	 * @inheritDoc
	 * @return array[]
	 */
	public function getBodyParamSettings(): array {
		return [
			'editRevId' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'filename' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'accepted' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'reasons' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => AddImageSubmissionHandler::REJECTION_REASONS,
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => false,
			],
			'caption' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'sectionTitle' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'sectionNumber' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			]
		] + $this->getTokenParamDefinition();
	}

	/**
	 * @inheritDoc
	 */
	public function validate( Validator $restValidator ) {
		parent::validate( $restValidator );
		$this->validateToken();
	}

	private function makeException( string $messageKey, array $params = [], int $errorCode = 400 ): Exception {
		return new LocalizedHttpException( new MessageValue( $messageKey, $params ), $errorCode );
	}

}
