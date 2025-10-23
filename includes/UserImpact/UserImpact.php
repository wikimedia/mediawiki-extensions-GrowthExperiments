<?php

namespace GrowthExperiments\UserImpact;

use JsonSerializable;
use LogicException;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Assert\Assert;
use Wikimedia\Assert\ParameterAssertionException;
use Wikimedia\JsonCodec\JsonCodecable;
use Wikimedia\JsonCodec\JsonCodecableTrait;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Value object representing a user's impact statistics.
 * This is generated data pieced together from contributions, thanks etc.
 * This is information relevant for new users, and not always realistic to include data
 * from arbitrarily long ago, so the data might use cutoffs that wouldn't affect a recently
 * registered user with a limited number of edits.
 */
class UserImpact implements JsonSerializable, JsonCodecable {
	use JsonCodecableTrait;

	/**
	 * Cache version, to be increased when breaking backwards compatibility.
	 *
	 * NOTE: This is not merely a cache version. Bumping it does more than purge the cache.
	 * It also changes the REST API output (UserImpactHandler) and invalidate any queued
	 * RefreshUserImpactJob jobs.
	 */
	public const VERSION = 12;

	private UserIdentity $user;
	private int $receivedThanksCount;
	private int $givenThanksCount;
	/** @var int[] */
	private array $editCountByNamespace;
	/** @var int[] */
	private array $editCountByDay;
	private int $revertedEditCount;
	private int $newcomerTaskEditCount;
	private ?int $lastEditTimestamp = null;
	private int $generatedAt;
	private EditingStreak $longestEditingStreak;
	private array $editCountByTaskType;

	private int $totalArticlesCreatedCount;
	/** @var int|null Copy of user.user_editcount */
	private ?int $totalUserEditCount;

	/**
	 * @param UserIdentity $user
	 * @param int $receivedThanksCount Number of thanks the user has received. Might exclude
	 *   thanks received a long time ago.
	 * @param int $givenThanksCount Number of thanks the user has given. Might exclude thanks
	 *    given a long time ago.
	 * @param int[] $editCountByNamespace Namespace ID => number of edits the user made in some
	 *   namespace. Might exclude edits made a long time ago or many edits ago.
	 * @param int[] $editCountByDay Day => number of edits the user made on that day. Indexed with
	 *   ISO 8601 dates, e.g. '2022-08-25'. Might exclude edits made many edits ago.
	 * @param array $editCountByTaskType
	 * @param int $revertedEditCount Number of edits by the user that got reverted (determined by
	 * the mw-reverted tag).
	 * @param int $newcomerTaskEditCount Number of edits the user made which have the
	 *   newcomer task tag. Might exclude edits made a long time ago or many edits ago.
	 * @param int|null $lastEditTimestamp Unix timestamp of the user's last edit.
	 * @param EditingStreak $longestEditingStreak
	 * @param int $totalArticlesCreatedCount Number of articles created by the user.
	 * @param int|null $totalUserEditCount Copy of user.user_editcount for the user
	 */
	public function __construct(
		UserIdentity $user,
		int $receivedThanksCount,
		int $givenThanksCount,
		array $editCountByNamespace,
		array $editCountByDay,
		array $editCountByTaskType,
		int $revertedEditCount,
		int $newcomerTaskEditCount,
		?int $lastEditTimestamp,
		EditingStreak $longestEditingStreak,
		int $totalArticlesCreatedCount,
		?int $totalUserEditCount
	) {
		$this->user = $user;
		$this->receivedThanksCount = $receivedThanksCount;
		$this->givenThanksCount = $givenThanksCount;
		$this->editCountByNamespace = $editCountByNamespace;
		$this->editCountByDay = $editCountByDay;
		$this->editCountByTaskType = $editCountByTaskType;
		$this->revertedEditCount = $revertedEditCount;
		$this->newcomerTaskEditCount = $newcomerTaskEditCount;
		$this->lastEditTimestamp = $lastEditTimestamp;
		$this->generatedAt = ConvertibleTimestamp::time();
		$this->longestEditingStreak = $longestEditingStreak;
		$this->totalUserEditCount = $totalUserEditCount;
		$this->totalArticlesCreatedCount = $totalArticlesCreatedCount;
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
	 */
	public function getReceivedThanksCount(): int {
		return $this->receivedThanksCount;
	}

	/**
	 * Number of thanks the user has given.
	 * Might exclude thanks given a long time ago.
	 */
	public function getGivenThanksCount(): int {
		return $this->givenThanksCount;
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
	 * Number of edits the user made which have the newcomer task tag.
	 * Might exclude edits made a long time ago or many edits ago.
	 */
	public function getNewcomerTaskEditCount(): int {
		return $this->newcomerTaskEditCount;
	}

	/**
	 * Number of newcomer task edits the user has made for each task type.
	 *
	 * @return array<string,int> (task type id => edit count for the task type)
	 */
	public function getEditCountByTaskType(): array {
		return $this->editCountByTaskType;
	}

	/**
	 * Number of total edits by the user that got reverted.
	 */
	public function getRevertedEditCount(): int {
		return $this->revertedEditCount;
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
	 */
	public function getGeneratedAt(): int {
		return $this->generatedAt;
	}

	/**
	 * Total number of edits across all namespaces.
	 *
	 * @note Unlike all other methods in this class, this one is not capped to recent edits (in
	 * other words, ComputedUserImpactLookup::MAX_EDITS is ignored).
	 * @return int
	 */
	public function getTotalEditsCount(): int {
		return $this->totalUserEditCount ?? array_sum( $this->editCountByNamespace );
	}

	public function getTotalArticlesCreatedCount(): int {
		return $this->totalArticlesCreatedCount;
	}

	/**
	 * Total number of edits for the user's longest editing streak.
	 *
	 * @return int Number of edits the user had in their longest editing streak
	 */
	public function getLongestEditingStreakCount(): int {
		return $this->longestEditingStreak->getTotalEditCountForPeriod();
	}

	/**
	 * Helper method for newFromJsonArray.
	 */
	protected static function newEmpty(): self {
		return new self(
			new UserIdentityValue( 0, '' ),
			0,
			0,
			[],
			[],
			[],
			0,
			0,
			0,
			new EditingStreak(),
			0,
			0
		);
	}

	/**
	 * @throws ParameterAssertionException when trying to load an incompatible old JSON format.
	 */
	public static function newFromJsonArray( array $json ): self {
		if ( array_key_exists( 'dailyTotalViews', $json ) ) {
			$userImpact = ExpensiveUserImpact::newEmpty();
		} elseif ( array_key_exists( 'topViewedArticles', $json ) ) {
			// UserImpactFormatter::jsonSerialize() unsets the 'dailyArticleViews'
			// field so deserializing it would be tricky, but it's not needed anyway.
			throw new LogicException( 'UserImpactFormatter is not deserializable.' );
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
		$this->givenThanksCount = $json['givenThanksCount'];
		$this->editCountByNamespace = $json['editCountByNamespace'];
		$this->editCountByDay = $json['editCountByDay'];
		$this->editCountByTaskType = $json['editCountByTaskType'];
		$this->totalUserEditCount = $json['totalUserEditCount'];
		$this->totalArticlesCreatedCount = $json['totalArticlesCreatedCount'];
		$this->revertedEditCount = $json['revertedEditCount'];
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
	public function toJsonArray(): array {
		return $this->jsonSerialize();
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		$longestEditingStreak = $this->longestEditingStreak->getDatePeriod() ?
			[ 'datePeriod' => [
				'start' => $this->longestEditingStreak->getDatePeriod()->getStartDate()->format( 'Y-m-d' ),
				'end' => $this->longestEditingStreak->getDatePeriod()->getEndDate()->format( 'Y-m-d' ),
				'days' => $this->longestEditingStreak->getStreakNumberOfDays(),
			], 'totalEditCountForPeriod' => $this->longestEditingStreak->getTotalEditCountForPeriod() ] :
			'';
		return [
			'@version' => self::VERSION,
			'userId' => $this->user->getId(),
			'userName' => $this->user->getName(),
			'receivedThanksCount' => $this->receivedThanksCount,
			'givenThanksCount' => $this->givenThanksCount,
			'editCountByNamespace' => $this->editCountByNamespace,
			'editCountByDay' => $this->editCountByDay,
			'editCountByTaskType' => $this->editCountByTaskType,
			'totalUserEditCount' => $this->totalUserEditCount,
			'revertedEditCount' => $this->revertedEditCount,
			'newcomerTaskEditCount' => $this->newcomerTaskEditCount,
			'lastEditTimestamp' => $this->lastEditTimestamp,
			'generatedAt' => $this->generatedAt,
			'longestEditingStreak' => $longestEditingStreak,
			'totalEditsCount' => $this->getTotalEditsCount(),
			'totalArticlesCreatedCount' => $this->getTotalArticlesCreatedCount(),
		];
	}

}
