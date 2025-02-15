<?php

namespace GrowthExperiments\Api;

use GrowthExperiments\Mentorship\Provider\AbstractStructuredMentorWriter;
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
		$mentorUsers = $this->mentorProvider->getMentors();
		foreach ( $mentorUsers as $mentorUser ) {
			$mentor = $this->mentorProvider->newMentorFromUserIdentity( $mentorUser );
			$result[$mentorUser->getId()] = AbstractStructuredMentorWriter::serializeMentor( $mentor );

			// for convenience of the consumers
			$result[$mentorUser->getId()]['username'] = $mentorUser->getName();
		}

		// NOTE: Continuation support is not implemented, because all mentors are always
		// stored in MediaWiki:GrowthMentors.json, which (being a regular wiki page) has no
		// continuation support either.
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}
}
