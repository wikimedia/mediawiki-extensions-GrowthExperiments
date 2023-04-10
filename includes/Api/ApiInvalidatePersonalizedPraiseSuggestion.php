<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\EventLogging\PersonalizedPraiseLogger;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;

class ApiInvalidatePersonalizedPraiseSuggestion extends ApiBase {

	private MentorProvider $mentorProvider;
	private MentorStore $mentorStore;
	private PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester;

	private PersonalizedPraiseLogger $personalizedPraiseLogger;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorProvider $mentorProvider
	 * @param MentorStore $mentorStore
	 * @param PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester
	 * @param PersonalizedPraiseLogger $personalizedPraiseLogger
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorProvider $mentorProvider,
		MentorStore $mentorStore,
		PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester,
		PersonalizedPraiseLogger $personalizedPraiseLogger
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorProvider = $mentorProvider;
		$this->mentorStore = $mentorStore;
		$this->praiseworthyMenteeSuggester = $praiseworthyMenteeSuggester;
		$this->personalizedPraiseLogger = $personalizedPraiseLogger;
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

		if ( $params['reason'] === 'praised' ) {
			$mentor = $this->mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY );
			if ( !$mentor ) {
				LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
					'ApiInvalidatePersonalizedPraiseSuggestion failed to load mentor for {mentee}',
					[ 'mentee' => $mentee->getName() ]
				);
				return;
			}
			$this->personalizedPraiseLogger->logPraised(
				$mentor,
				$mentee
			);
		}
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
			],
			'reason' => [
				ParamValidator::PARAM_TYPE => [
					'skip',
					'praised'
				],
			]
		];
	}
}
