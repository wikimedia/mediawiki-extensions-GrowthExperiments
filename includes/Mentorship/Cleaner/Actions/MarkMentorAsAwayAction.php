<?php

namespace GrowthExperiments\Mentorship\Cleaner\Actions;

use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\Cleaner\LastActionTimestampLookup;
use GrowthExperiments\Mentorship\Provider\IMentorWriter;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use LogicException;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\UserIdentity;
use StatusValue;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ParamValidator\TypeDef\ExpiryDef;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

class MarkMentorAsAwayAction implements IAction {

	/**
	 * Keep a ~day more than cleanMentorList.php's run frequency (currently runs every ~3 days).
	 */
	private const int PROLONG_AWAYNESS_SECONDS_BEFORE = 4 * 86_400;

	public function __construct(
		private MentorProvider $mentorProvider,
		private IMentorWriter $mentorWriter,
		private MentorStatusManager $mentorStatusManager,
		private LastActionTimestampLookup $lastActionTimestampLookup,
		private UserIdentity $systemPerformer,
		private bool $isEnabled,
		private int $minDaysSinceLastEdit,
		private int $awayDurationInDays,
	) {
	}

	public function isEnabled(): bool {
		return $this->isEnabled;
	}

	public function check( UserIdentity $user ): bool {
		if ( !$this->mentorStatusManager->canChangeStatus( $user )->isOK() ) {
			// Means the user is forcefully marked as away already (block, ...); no point in
			// running our stuff.
			return false;
		}
		if ( $this->mentorStatusManager->getMentorStatus( $user ) === MentorStatusManager::STATUS_AWAY ) {
			// Only renew the awayness when it expires in less than PROLONG_AWAYNESS_SECONDS_BEFORE
			// seconds. This will avoid repetitive updates to the list of mentors. See T436659
			// for more details. If needed, the mentor's awayness will be restored before it clears.
			$secondsUntilBack = (int)ConvertibleTimestamp::convert(
				TimestampFormat::UNIX, $this->mentorStatusManager->getMentorBackTimestamp( $user )
			) - (int)ConvertibleTimestamp::now( TimestampFormat::UNIX );

			// If the mentor is back in less than the allowed time, they'll be checked for
			// inactivity and processed normally.
			if ( $secondsUntilBack > self::PROLONG_AWAYNESS_SECONDS_BEFORE ) {
				return false;
			}
		}

		$lastEditTimestamp = $this->lastActionTimestampLookup->getLastActionTimestampForUser( $user );
		if ( !$lastEditTimestamp ) {
			return true;
		}

		$secondsSinceLastEdit = (int)ConvertibleTimestamp::now( TimestampFormat::UNIX ) -
			(int)ConvertibleTimestamp::convert( TimestampFormat::UNIX, $lastEditTimestamp );
		if ( $secondsSinceLastEdit < 0 ) {
			throw new LogicException( $user->getName() . ' edited in the future' );
		}

		return $secondsSinceLastEdit / ExpirationAwareness::TTL_DAY > $this->minDaysSinceLastEdit;
	}

	public function perform( UserIdentity $user, MessageLocalizer $messageLocalizer ): StatusValue {
		$mentor = $this->mentorProvider->newMentorFromUserIdentity( $user );

		$awayTimestamp = ExpiryDef::normalizeExpiry( sprintf( '%d days', $this->awayDurationInDays ) )
			->getTimestamp( TimestampFormat::MW );
		$mentor->setAwayTimestamp( $awayTimestamp );

		$result = StatusValue::newGood();
		$result->merge( $this->mentorStatusManager->markMentorAsAwayTimestamp( $user, $awayTimestamp ) );
		$result->merge( $this->mentorWriter->changeMentor(
			$mentor,
			$this->systemPerformer,
			$messageLocalizer->msg( 'growthexperiments-mentor-list-cleaner-mark-mentor-as-away-action' )
				->params( $user->getName() )
				->numParams( $this->minDaysSinceLastEdit )
				->inContentLanguage()
				->text()
		) );
		return $result;
	}
}
