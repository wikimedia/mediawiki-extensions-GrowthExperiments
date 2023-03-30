<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;

class ApiInvalidatePersonalizedPraiseSuggestion extends ApiBase {

	private MentorProvider $mentorProvider;
	private PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorProvider $mentorProvider
	 * @param PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorProvider $mentorProvider,
		PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorProvider = $mentorProvider;
		$this->praiseworthyMenteeSuggester = $praiseworthyMenteeSuggester;
	}

	/** @inheritDoc */
	public function execute() {
		if ( !$this->mentorProvider->isMentor( $this->getUser() ) ) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$params = $this->extractRequestParams();
		/** @var UserIdentity $mentee */
		$mentee = $params['mentee'];

		$this->praiseworthyMenteeSuggester->markMenteeAsPraised( $mentee );
		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
			'mentee' => $mentee->getName(),
		] );
	}

	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function isInternal() {
		return true;
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		return [
			'mentee' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'id' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			]
		];
	}
}
