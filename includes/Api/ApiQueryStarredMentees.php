<?php

namespace GrowthExperiments\Api;

use ApiQuery;
use ApiQueryBase;
use GrowthExperiments\MentorDashboard\MenteeOverview\StarredMenteesStore;

class ApiQueryStarredMentees extends ApiQueryBase {
	/** @var StarredMenteesStore */
	private $starredMenteesStore;

	/**
	 * @param ApiQuery $mainModule
	 * @param string $moduleName
	 * @param StarredMenteesStore $starredMenteesStore
	 */
	public function __construct(
		ApiQuery $mainModule,
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
		if ( !$this->getUser()->isNamed() ) {
			$this->dieWithError( [ 'apierror-permissiondenied-generic' ] );
		}

		$res = [];
		$starredMentees = $this->starredMenteesStore->getStarredMentees( $this->getUser() );
		foreach ( $starredMentees as $user ) {
			$res[] = [
				'id' => $user->getId(),
				'username' => $user->getName()
			];
		}
		$this->getResult()->addValue( null, $this->getModuleName(), [
			'mentees' => $res
		] );
	}
}
