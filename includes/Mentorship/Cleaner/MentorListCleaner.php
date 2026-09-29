<?php

namespace GrowthExperiments\Mentorship\Cleaner;

use GrowthExperiments\Mentorship\Cleaner\Actions\ActionFactory;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Language\MessageLocalizer;
use Psr\Log\LoggerInterface;
use StatusValue;

class MentorListCleaner {

	public function __construct(
		private ActionFactory $actionFactory,
		private MentorProvider $mentorProvider,
		private LoggerInterface $logger
	) {
	}

	public function processMentors( MessageLocalizer $messageLocalizer ): StatusValue {
		$status = StatusValue::newGood();
		$mentors = $this->mentorProvider->getMentors();
		foreach ( ActionFactory::ACTIONS as $actionName ) {
			$action = $this->actionFactory->newFromClassName( $actionName );
			if ( !$action->isEnabled() ) {
				// No point in doing anything
				$this->logger->info( __METHOD__ . ' skipping {action}, because it is not enabled', [
					'action' => $actionName,
				] );
				continue;
			}

			foreach ( $mentors as $mentor ) {
				$mentorUser = $mentor->getUserIdentity();
				if ( !$action->check( $mentorUser ) ) {
					$this->logger->info( '{action}::check returned false for {mentor}', [
						'action' => $actionName,
						'mentor' => $mentorUser->getName(),
					] );
					continue;
				}

				$status->merge( $action->perform( $mentorUser, $messageLocalizer ) );
			}
		}

		return $status;
	}

}
