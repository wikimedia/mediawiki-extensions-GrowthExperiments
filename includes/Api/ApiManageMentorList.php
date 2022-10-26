<?php

namespace GrowthExperiments\Api;

use ApiBase;
use ApiMain;
use GrowthExperiments\MentorDashboard\MentorTools\MentorWeightManager;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use LogicException;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use User;
use Wikimedia\ParamValidator\ParamValidator;

class ApiManageMentorList extends ApiBase {

	/** @var MentorProvider */
	private $mentorProvider;

	/** @var IMentorWriter */
	private $mentorWriter;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MentorProvider $mentorProvider
	 * @param IMentorWriter $mentorWriter
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		MentorProvider $mentorProvider,
		IMentorWriter $mentorWriter
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mentorProvider = $mentorProvider;
		$this->mentorWriter = $mentorWriter;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( $this->getConfig()->get( 'GEMentorProvider' ) !== MentorProvider::PROVIDER_STRUCTURED ) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

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
		$mentor->setAutoAssigned( $params['autoassigned'] );

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

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'status' => 'ok',
			'mentor' => [
				'message' => $mentor->hasCustomIntroText() ? $mentor->getIntroText() : null,
				'weight' => $mentor->getWeight(),
				'automaticallyAssigned' => $mentor->getAutoAssigned(),
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
				ParamValidator::PARAM_TYPE => array_map( 'strval', MentorWeightManager::WEIGHTS )
			],
			'autoassigned' => [
				ParamValidator::PARAM_TYPE => 'boolean',
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
