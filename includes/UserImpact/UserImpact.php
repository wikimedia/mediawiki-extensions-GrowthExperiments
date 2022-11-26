<?php

namespace GrowthExperiments\UserImpact;

use DateTime;
use JsonSerializable;
use LogicException;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserTimeCorrection;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Value object representing a user's impact statistics.
 * This is generated data pieced together from contributions, thanks etc.
 * This is information relevant for new users, and not always realistic to include data
 * from arbitrarily long ago, so the data might use cutoffs that wouldn't affect a recently
 * registered user with a limited number of edits.
 */
class UserImpact implements JsonSerializable {

	/** Cache version, to be increased when breaking backwards compatibility. */
	public const VERSION = 5;

	/** @var UserIdentity */
	private $user;

	/** @var int */
	private $receivedThanksCount;

	/** @var int[] */
	private $editCountByNamespace;

	/** @var int[] */
	private $editCountByDay;

	/** @var UserTimeCorrection */
	private $timeZone;

	/** @var int */
	private $newcomerTaskEditCount;

	/** @var int|null */
	private $lastEditTimestamp;

	/** @var int */
	private $generatedAt;

	/** @var EditingStreak */
	private EditingStreak $longestEditingStreak;

	/**
	 * @param UserIdentity $user
	 * @param int $receivedThanksCount Number of thanks the user has received. Might exclude
	 *   thanks received a long time ago.
	 * @param int[] $editCountByNamespace Namespace ID => number of edits the user made in some
	 *   namespace. Might exclude edits made a long time ago or many edits ago.
	 * @param int[] $editCountByDay Day => number of edits the user made on that day. Indexed with
	 *   ISO 8601 dates, e.g. '2022-08-25'. Might exclude edits made many edits ago.
	 * @param UserTimeCorrection $timeZone The timezone used to define what a day means, typically
	 *   the timezone of the user.
	 * @param int $newcomerTaskEditCount Number of edits the user made which have the
	 *   newcomer task tag. Might exclude edits made a long time ago or many edits ago.
	 * @param int|null $lastEditTimestamp Unix timestamp of the user's last edit.
	 * @param EditingStreak $longestEditingStreak
	 */
	public function __construct(
		UserIdentity $user,
		int $receivedThanksCount,
		array $editCountByNamespace,
		array $editCountByDay,
		UserTimeCorrection $timeZone,
		int $newcomerTaskEditCount,
		?int $lastEditTimestamp,
		EditingStreak $longestEditingStreak
	) {
		$this->user = $user;
		$this->receivedThanksCount = $receivedThanksCount;
		$this->editCountByNamespace = $editCountByNamespace;
		$this->editCountByDay = $editCountByDay;
		$this->timeZone = $timeZone;
		$this->newcomerTaskEditCount = $newcomerTaskEditCount;
		$this->lastEditTimestamp = $lastEditTimestamp;
		$this->generatedAt = ConvertibleTimestamp::time();
		$this->longestEditingStreak = $longestEditingStreak;
	}

	/**
	 * Get the user whose impact this object represents.
	 * @return UserIdentity
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Number of thanks the user has received.
	 * Might exclude thanks received a long time ago.
	 * @return int
	 */
	public function getReceivedThanksCount(): int {
		return $this->receivedThanksCount;
	}

	/**
	 * Map of namespace ID => number of edits the user made in that namespace.
	 * Might exclude edits made a long time ago or many edits ago.
	 * @return int[]
	 */
	public function getEditCountByNamespace(): array {
		return $this->editCountByNamespace;
	}

	/**
	 * Number of edits the user made in the given namespace.
	 * Might exclude edits made a long time ago or many edits ago.
	 * @param int $namespace
	 * @return int
	 */
	public function getEditCountIn( int $namespace ): int {
		return $this->editCountByNamespace[$namespace] ?? 0;
	}

	/**
	 * Map of day => number of article-space edits the user made on that day.
	 * Indexed with ISO 8601 dates, e.g. '2022-08-25'; in ascending order by date.
	 * Dates aren't contiguous. Might exclude edits made many edits ago.
	 * @return int[]
	 */
	public function getEditCountByDay(): array {
		return $this->editCountByDay;
	}

	/**
	 * The timezone used to define what a day means, typically the timezone of the user.
	 * @return UserTimeCorrection
	 */
	public function getTimeZone(): UserTimeCorrection {
		return $this->timeZone;
	}

	/**
	 * Number of edits the user made which have the newcomer task tag.
	 * Might exclude edits made a long time ago or many edits ago.
	 * @return int
	 */
	public function getNewcomerTaskEditCount(): int {
		return $this->newcomerTaskEditCount;
	}

	/**
	 * Unix timestamp of the user's last edit, or null if the user has zero edits.
	 * @return int|null
	 */
	public function getLastEditTimestamp(): ?int {
		return $this->lastEditTimestamp;
	}

	/**
	 * Unix timestamp of when the user impact data was generated.
	 * @return int
	 */
	public function getGeneratedAt(): int {
		return $this->generatedAt;
	}

	/**
	 * Total number of edits across all namespaces.
	 *
	 * @return int
	 */
	public function getTotalEditsCount(): int {
		return array_sum( $this->editCountByNamespace );
	}

	/**
	 * Total number of days of the user's longest editing streak.
	 *
	 * @return int
	 */
	public function getLongestEditingStreakCount(): int {
		return $this->longestEditingStreak->getTotalEditCountForPeriod();
	}

	/**
	 * Helper method for newFromJsonArray.
	 * @return UserImpact
	 */
	protected static function newEmpty(): self {
		return new UserImpact(
			new UserIdentityValue( 0, '' ),
			0,
			[],
			[],
			new UserTimeCorrection( 'System|0' ),
			0,
			0,
			new EditingStreak()
		);
	}

	/**
	 * @param array $json
	 * @return UserImpact
	 * @throws ParameterAssertionException when trying to load an incompatible old JSON format.
	 */
	public static function newFromJsonArray( array $json ): UserImpact {
		if ( array_key_exists( 'dailyTotalViews', $json ) ) {
			$userImpact = ExpensiveUserImpact::newEmpty();
		} elseif ( array_key_exists( 'topViewedArticles', $json ) ) {
			// UserImpactFormatter::jsonSerialize() unsets the 'dailyArticleViews'
			// field so unserializing it would be tricky, but it's not needed anyway.
			throw new LogicException( 'UserImpactFormatter is not unserializable.' );
		} else {
			$userImpact = self::newEmpty();
		}
		$userImpact->loadFromJsonArray( $json );
		return $userImpact;
	}

	/**
	 * @param array $json
	 * @throws ParameterAssertionException when trying to load an incompatible old JSON format.
	 */
	protected function loadFromJsonArray( array $json ): void {
		if ( $json['@version'] !== self::VERSION ) {
			throw new ParameterAssertionException( '@version', 'must be ' . self::VERSION );
		}

		Assert::parameterKeyType( 'integer', $json['editCountByNamespace'], '$json[\'editCountByNamespace\']' );
		Assert::parameterElementType( 'integer', $json['editCountByNamespace'], '$json[\'editCountByNamespace\']' );
		Assert::parameterKeyType( 'string', $json['editCountByDay'], '$json[\'editCountByDay\']' );
		Assert::parameterElementType( 'integer', $json['editCountByDay'], '$json[\'editCountByDay\']' );

		$this->user = UserIdentityValue::newRegistered( $json['userId'], $json['userName'] );
		$this->receivedThanksCount = $json['receivedThanksCount'];
		$this->editCountByNamespace = $json['editCountByNamespace'];
		$this->editCountByDay = $json['editCountByDay'];
		// Make the time correction object testing friendly - otherwise it would contain a
		// current-time DateTime object.
		$date = new DateTime( '@' . ConvertibleTimestamp::time() );
		$this->timeZone = new UserTimeCorrection( $json['timeZone'][0], $date, $json['timeZone'][1] );
		$this->newcomerTaskEditCount = $json['newcomerTaskEditCount'];
		$this->lastEditTimestamp = $json['lastEditTimestamp'];
		$this->generatedAt = $json['generatedAt'];
		$this->longestEditingStreak = $json['longestEditingStreak'] === '' ? new EditingStreak() :
			new EditingStreak(
				ComputeEditingStreaks::makeDatePeriod(
					$json['longestEditingStreak']['datePeriod']['start'],
					$json['longestEditingStreak']['datePeriod']['end']
				),
				$json['longestEditingStreak']['totalEditCountForPeriod']
			);
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		$longestEditingStreak = $this->longestEditingStreak->getDatePeriod() ?
			[ 'datePeriod' => [
				'start' => $this->longestEditingStreak->getDatePeriod()->getStartDate()->format( 'Y-m-d' ),
				'end' => $this->longestEditingStreak->getDatePeriod()->getEndDate()->format( 'Y-m-d' ),
				'days' => $this->longestEditingStreak->getStreakNumberOfDays()
			], 'totalEditCountForPeriod' => $this->longestEditingStreak->getTotalEditCountForPeriod() ] :
			'';
		return [
			'@version' => self::VERSION,
			'userId' => $this->user->getId(),
			'userName' => $this->user->getName(),
			'receivedThanksCount' => $this->receivedThanksCount,
			'editCountByNamespace' => $this->editCountByNamespace,
			'editCountByDay' => $this->editCountByDay,
			'timeZone' => [ $this->timeZone->toString(), $this->timeZone->getTimeOffset() ],
			'newcomerTaskEditCount' => $this->newcomerTaskEditCount,
			'lastEditTimestamp' => $this->lastEditTimestamp,
			'generatedAt' => $this->generatedAt,
			'longestEditingStreak' => $longestEditingStreak,
			'totalEditsCount' => $this->getTotalEditsCount()
		];
	}

}
