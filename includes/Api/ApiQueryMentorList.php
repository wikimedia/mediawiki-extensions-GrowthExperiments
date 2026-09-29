<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\Mentorship\Provider\CommunityStructuredMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;

class ApiQueryMentorList extends ApiQueryBase {

	private MentorProvider $mentorProvider;

	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		MentorProvider $mentorProvider
	) {
		parent::__construct( $queryModule, $moduleName );
		$this->mentorProvider = $mentorProvider;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$result = [];
		foreach ( $this->mentorProvider->getMentors() as $userId => $mentor ) {
			$result[$userId] = CommunityStructuredMentorWriter::serializeMentor( $mentor );

			// for convenience of the consumers
			$result[$userId]['username'] = $mentor->getUserIdentity()->getName();
		}

		// NOTE: Continuation support is not implemented, because all mentors are always
		// stored in MediaWiki:GrowthMentors.json, which (being a regular wiki page) has no
		// continuation support either.
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}
}
