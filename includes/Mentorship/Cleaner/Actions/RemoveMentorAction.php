<?php

namespace GrowthExperiments\Mentorship\Cleaner\Actions;

use GrowthExperiments\Mentorship\Cleaner\LastActionTimestampLookup;
use GrowthExperiments\Mentorship\MentorRemover;
use LogicException;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\User\UserIdentity;
use StatusValue;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use Wikimedia\Timestamp\TimestampFormat;

class RemoveMentorAction implements IAction {

	public function __construct(
		private MentorRemover $mentorRemover,
		private LastActionTimestampLookup $lastActionTimestampLookup,
		private UserIdentity $systemPerformer,
		private bool $isEnabled,
		private int $minDaysSinceLastEdit
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
		return $this->mentorRemover->removeMentor(
			$this->systemPerformer,
			$user,
			$messageLocalizer->msg( 'growthexperiments-mentor-list-cleaner-remove-mentor-action' )
				->params( $user->getName() )
				->numParams( $this->minDaysSinceLastEdit )
				->inContentLanguage()
				->text(),
			$messageLocalizer
		);
	}
}
