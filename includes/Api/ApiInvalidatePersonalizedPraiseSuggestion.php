<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\EventLogging\PersonalizedPraiseLogger;
use GrowthExperiments\MentorDashboard\PersonalizedPraise\PraiseworthyMenteeSuggester;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;

class ApiInvalidatePersonalizedPraiseSuggestion extends ApiBase {

	private MentorProvider $mentorProvider;
	private MentorStore $mentorStore;
	private PraiseworthyMenteeSuggester $praiseworthyMenteeSuggester;

	private PersonalizedPraiseLogger $personalizedPraiseLogger;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
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

		if ( $params['reason'] === PersonalizedPraiseLogger::ACTION_PRAISED ) {
			$this->praiseworthyMenteeSuggester->markMenteeAsPraised( $mentee );
		} elseif ( $params['reason'] === PersonalizedPraiseLogger::ACTION_SKIPPED ) {
			$this->praiseworthyMenteeSuggester->markMenteeAsSkipped( $mentee );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
			'mentee' => $mentee->getName(),
		] );

		$mentor = $this->mentorStore->loadMentorUser( $mentee, MentorStore::ROLE_PRIMARY );
		if ( !$mentor ) {
			LoggerFactory::getInstance( 'GrowthExperiments' )->warning(
				'ApiInvalidatePersonalizedPraiseSuggestion failed to load mentor for {mentee}',
				[ 'mentee' => $mentee->getName() ]
			);
			return;
		}

		if ( $params['reason'] === PersonalizedPraiseLogger::ACTION_PRAISED ) {
			$this->personalizedPraiseLogger->logPraised(
				$mentor,
				$mentee
			);
		} elseif ( $params['reason'] === PersonalizedPraiseLogger::ACTION_SKIPPED ) {
			$this->personalizedPraiseLogger->logSkipped(
				$mentor,
				$mentee,
				$params['skipreason']
			);
		}
	}

	/** @inheritDoc */
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
					'skipped',
					'praised',
				],
			],
			'skipreason' => [
				// NOTE: Keep in sync with SkipMenteeDialog.vue's reasonItems
				ParamValidator::PARAM_TYPE => [
					'already-praised',
					'not-praiseworthy',
					'not-now',
					'other',
				],
			],
		];
	}
}
