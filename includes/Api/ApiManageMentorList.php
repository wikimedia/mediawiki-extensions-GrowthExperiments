<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use LogicException;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use User;
use Wikimedia\ParamValidator\ParamValidator;

class ApiManageMentorList extends ApiBase {

	private MentorProvider $mentorProvider;
	private IMentorWriter $mentorWriter;
	private MentorStatusManager $mentorStatusManager;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 * @param MentorStatusManager $mentorStatusManager
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter,
		MentorStatusManager $mentorStatusManager
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
		$this->mentorStatusManager = $mentorStatusManager;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$block = $this->getUser()->getBlock( User::READ_LATEST );
		if ( $block && $block->isSitewide() ) {
			$this->dieBlocked( $block );
		}

		// if the user is not a mentor, require enrollasmentor or managementors; the if is here to
		// allow users to remove themselves, even after they lost the ability to enroll themselves.
		if ( !$this->mentorProvider->isMentor( $this->getUser() ) ) {
			$this->checkUserRightsAny( [ 'enrollasmentor', 'managementors' ] );
		}

		$params = $this->extractRequestParams();

		if ( $params['username'] !== null ) {
			$this->checkUserRightsAny( 'managementors' );
			// despite the name, $params['username'] is converted to an UserIdentity
			// via UserDef::PARAM_RETURN_OBJECT in getAllowedParams().
			$mentorUser = $params['username'];
		} else {
			$mentorUser = $this->getUser();
		}

		$mentor = $this->mentorProvider->newMentorFromUserIdentity( $mentorUser );

		if ( $params['message'] !== null ) {
			$mentor->setIntroText( $params['message'] !== '' ? $params['message'] : null );
		}
		if ( $params['weight'] !== null ) {
			$mentor->setWeight( (int)$params['weight'] );
		}

		// ensure awaytimestamp is provided when isaway=true
		if ( $params['isaway'] && $params['awaytimestamp'] === null ) {
			$this->dieWithError(
				'growthexperiments-api-managementors-error-no-away-timestamp',
				'away-timestamp'
			);
		}

		switch ( $params['geaction'] ) {
			case 'add':
				$statusValue = $this->mentorWriter->addMentor(
					$mentor,
					$this->getUser(),
					$params['summary']
				);
				break;
			case 'change':
				$statusValue = $this->mentorWriter->changeMentor(
					$mentor,
					$this->getUser(),
					$params['summary']
				);
				break;
			case 'remove':
				$statusValue = $this->mentorWriter->removeMentor(
					$mentor,
					$this->getUser(),
					$params['summary']
				);
				break;
			default:
				// this should never happen, unless getAllowedParams is wrong
				throw new LogicException( 'Invalid geaction passed validation' );
		}

		if ( !$statusValue->isOK() ) {
			$this->dieStatus( $statusValue );
		}

		if ( $params['geaction'] !== 'remove' ) {
			if ( $params['isaway'] ) {
				$result = $this->mentorStatusManager->markMentorAsAwayTimestamp(
					$mentorUser,
					$params['awaytimestamp']
				);
				if ( !$result->isOK() ) {
					$this->dieStatus( $result );
				}
			} else {
				$this->mentorStatusManager->markMentorAsActive( $mentorUser );
			}
		}

		$rawBackTs = $this->mentorStatusManager->getMentorBackTimestamp( $mentorUser );
		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
			'mentor' => [
				'message' => $mentor->hasCustomIntroText() ? $mentor->getIntroText() : null,
				'weight' => $mentor->getWeight(),
				'awayTimestamp' => $rawBackTs,
				'awayTimestampHuman' => $rawBackTs !== null ? $this->getContext()
					->getLanguage()->date( $rawBackTs, true ) : null,

				// NOTE: Legacy attribute, weight provides the same info.
				'automaticallyAssigned' => $mentor->getWeight() !== IMentorWeights::WEIGHT_NONE,
			]
		] );
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
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	protected function getAllowedParams() {
		return [
			'geaction' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => [
					'add',
					'change',
					'remove',
				]
			],
			'message' => [
				ParamValidator::PARAM_TYPE => 'string'
			],
			'weight' => [
				ParamValidator::PARAM_TYPE => array_map( 'strval', IMentorWeights::WEIGHTS )
			],
			'isaway' => [
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'awaytimestamp' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
			],
			'summary' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'username' => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
		];
	}
}
