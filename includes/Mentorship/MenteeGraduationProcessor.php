<?php

namespace GrowthExperiments\Mentorship;

use GrowthExperiments\Mentorship\Store\MentorStore;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

class MenteeGraduationProcessor {

	public function __construct(
		private LoggerInterface $logger,
		private MentorStore $mentorStore,
		private MenteeGraduation $menteeGraduation
	) {
	}

	/**
	 * Is the feature enabled?
	 *
	 * @see MenteeGraduation::getIsEnabled()
	 */
	public function isEnabled(): bool {
		return $this->menteeGraduation->getIsEnabled();
	}

	/**
	 * Graduate all eligible mentees assigned to one mentor
	 *
	 * @param UserIdentity $mentor
	 * @param bool $dryRun Do not actually reassign anyone (only return the count)
	 * @return int Number of mentees graduated
	 */
	private function doGraduateEligibleMenteesByMentor( UserIdentity $mentor, bool $dryRun ): int {
		$this->logger->info( __METHOD__ . ' graduating mentees of {mentor}', [
			'mentor' => $mentor->getName(),
		] );

		$mentees = $this->mentorStore->getMenteesByMentor( $mentor, MentorStore::ROLE_PRIMARY );
		$graduatedNo = 0;
		foreach ( $mentees as $mentee ) {
			if ( $this->menteeGraduation->shouldUserBeGraduated( $mentee ) ) {
				if ( !$dryRun ) {
					$this->menteeGraduation->graduateUserFromMentorship( $mentee );
				}
				$graduatedNo++;
			}
		}
		return $graduatedNo;
	}

	/**
	 * Graduate all eligible mentees assigned to one mentor
	 *
	 * @return int Number of mentees graduated
	 */
	public function graduateEligibleMenteesByMentor( UserIdentity $mentor ): int {
		return $this->doGraduateEligibleMenteesByMentor( $mentor, false );
	}

	/**
	 * Calculate how many mentees assigned to given mentor would be graduated
	 *
	 * @internal for the GraduateEligibleMentees maintenance script
	 * @return int
	 */
	public function calculateEligibleMenteesByMentor( UserIdentity $mentor ): int {
		return $this->doGraduateEligibleMenteesByMentor( $mentor, true );
	}
}
