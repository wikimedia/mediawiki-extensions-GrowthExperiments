<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;

class ApiStarMentee extends ApiBase {
	private StarredMenteesStore $starredMenteesStore;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		StarredMenteesStore $starredMenteesStore
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->starredMenteesStore = $starredMenteesStore;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->getUser()->isNamed() ) {
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
					'unstar',
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
