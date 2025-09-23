<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\User\UserIdentity;
use Wikimedia\ParamValidator\ParamValidator;

class ApiQueryMentorMentee extends ApiQueryBase {

	private MentorStore $mentorStore;

	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		MentorStore $mentorStore
	) {
		parent::__construct( $queryModule, $moduleName );

		$this->mentorStore = $mentorStore;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();

		/** @var UserIdentity $mentor */
		$mentor = $params['gemmmentor'];

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'mentor' => $mentor->getName(),
			'mentees' => array_map( static function ( UserIdentity $mentee ) {
				return [
					'name' => $mentee->getName(),
					'id' => $mentee->getId(),
				];
			}, $this->mentorStore->getMenteesByMentor(
				$mentor,
				MentorStore::ROLE_PRIMARY
			) ),
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getAllowedParams() {
		return [
			'gemmmentor' => [
				ParamValidator::PARAM_TYPE => 'user',
				ParamValidator::PARAM_REQUIRED => true,
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'id' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
		];
	}
}
