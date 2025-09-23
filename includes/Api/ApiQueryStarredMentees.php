<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;

class ApiQueryStarredMentees extends ApiQueryBase {
	private StarredMenteesStore $starredMenteesStore;

	public function __construct(
		ApiQuery $mainModule,
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

		$res = [];
		$starredMentees = $this->starredMenteesStore->getStarredMentees( $this->getUser() );
		foreach ( $starredMentees as $user ) {
			$res[] = [
				'id' => $user->getId(),
				'username' => $user->getName(),
			];
		}
		$this->getResult()->addValue( null, $this->getModuleName(), [
			'mentees' => $res,
		] );
	}
}
