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
