<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;

class ApiStarMentee extends ApiBase {
	/** @var StarredMenteesStore */
	private $starredMenteesStore;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param StarredMenteesStore $starredMenteesStore
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		StarredMenteesStore $starredMenteesStore
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->starredMenteesStore = $starredMenteesStore;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->getUser()->isRegistered() ) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$params = $this->extractRequestParams();
		$mentor = $this->getUser();
		$action = $params['gesaction'];
		/** @var UserIdentity */
		$mentee = $params['gesmentee'];

		if ( $action === 'star' ) {
			$this->starredMenteesStore->starMentee(
				$mentor,
				$mentee
			);
		} elseif ( $action === 'unstar' ) {
			$this->starredMenteesStore->unstarMentee(
				$mentor,
				$mentee
			);
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
			'action' => $action,
			'mentee' => $mentee->getName(),
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'gesaction' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => [
					'star',
					'unstar'
				],
			],
			'gesmentee' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'id' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
		];
	}
}
