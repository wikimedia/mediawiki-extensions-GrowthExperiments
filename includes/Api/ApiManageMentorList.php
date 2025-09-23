<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\Config\Validation\StatusAwayValidator;
use GrowthExperiments\MentorDashboard\MentorTools\IMentorWeights;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use LogicException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use StatusValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDBAccessObject;

class ApiManageMentorList extends ApiBase {

	private MentorProvider $mentorProvider;
	private IMentorWriter $mentorWriter;
	private MentorStatusManager $mentorStatusManager;

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
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
		$block = $this->getUser()->getBlock( IDBAccessObject::READ_LATEST );
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
		$canMakeUpdate = $this->canMakeUpdate( $mentorUser, $params );
		if ( !$canMakeUpdate->isOK() ) {
			$this->dieStatus( $canMakeUpdate );
		}
		$awayTimestamp = $params['isaway'] && $params['awaytimestamp'] ? $params['awaytimestamp'] : null;
		if ( $awayTimestamp ) {
			$validationStatus = StatusAwayValidator::validateTimestamp( $awayTimestamp, $mentorUser->getId() );
			if ( !$validationStatus->isOK() ) {
				$this->dieStatus( $validationStatus );
			}
			$mentor->setAwayTimestamp( $awayTimestamp );
		} else {
			$mentor->setAwayTimestamp( null );
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
			// TODO remove after running migration script and after we start reading from config in
			// MentorStatusManager::getAwayMentors and getAwayReason (T347152)
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
			],
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
				],
			],
			'message' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'weight' => [
				ParamValidator::PARAM_TYPE => array_map( 'strval', IMentorWeights::WEIGHTS ),
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

	private function canMakeUpdate( UserIdentity $mentorUser, array $params ): StatusValue {
		$needsStatusCheck = ( $params['geaction'] === 'add' || $params['geaction'] === 'change' ) && $params['isaway'];
		if ( !$needsStatusCheck ) {
			return StatusValue::newGood();
		}
		return $this->mentorStatusManager->canChangeStatus( $mentorUser, IDBAccessObject::READ_LATEST );
	}
}
