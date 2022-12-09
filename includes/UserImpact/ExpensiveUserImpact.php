<?php

namespace GrowthExperiments\UserImpact;

use DateTime;
use Exception;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserTimeCorrection;
use MWTimestamp;
use Wikimedia\Assert\Assert;

/**
 * Value object representing a user's impact statistics, including those which are
 * more expensive to retrieve.
 */
class ExpensiveUserImpact extends UserImpact {

	/** @var int[] */
	private array $dailyTotalViews;

	/** @var array */
	private array $dailyArticleViews;

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
	 * @param int[] $dailyTotalViews Day => number of total pageviews the articles edited by the user
	 *   (on any day) got on that day. Indexed with ISO 8601 dates, e.g. '2022-08-25'.
	 *   Might exclude edits made many days or many edits ago.
	 * @param array $dailyArticleViews @see ::getDailyArticleViews
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
		array $dailyTotalViews,
		array $dailyArticleViews,
		EditingStreak $longestEditingStreak
	) {
		parent::__construct( $user, $receivedThanksCount, $editCountByNamespace, $editCountByDay,
			$timeZone, $newcomerTaskEditCount, $lastEditTimestamp, $longestEditingStreak );
		$this->dailyTotalViews = $dailyTotalViews;
		$this->dailyArticleViews = $dailyArticleViews;
	}

	/**
	 * Day => number of total pageviews the articles edited by the user (on any day) got on that day.
	 * Indexed with ISO 8601 dates, e.g. '2022-08-25'. The list of days is contiguous, in ascending
	 * order, and ends more or less at the current day (might be a few days off to account for data
	 * collection lags).
	 * Might exclude edits made many days or many edits ago.
	 * @return int[]
	 */
	public function getDailyTotalViews(): array {
		return $this->dailyTotalViews;
	}

	/**
	 * An array with the title's prefixed DBkey as the key. The values contain:
	 * - firstEditDate: The date the user first edited the article, in Y-m-d format.
	 *   If the user made a very high number of total edits, it might just be some edit the
	 *   user made to the article, not necessarily the first.
	 * - newestEdit: The TS_MW timestamp of the newest edit by the user to the article.
	 * - views: An array of page view counts. Each row in the array has a key with the date in
	 *   Y-m-d format and the value is the page view count total for that day.
	 *   The list of days is contiguous, in ascending order, and ends more or less at the current
	 *   day (might be a few days off to account for data collection lags).
	 * - imageUrl: URL of a thumbnail of the article's main image, or null if there's no main image.
	 * @return array[]
	 * @phan-return array<string,array{views:array<string,int>,firstEditDate:string,newestEdit:string,imageUrl:?string}>
	 */
	public function getDailyArticleViews(): array {
		return $this->dailyArticleViews;
	}

	/** @inheritDoc */
	protected static function newEmpty(): self {
		return new ExpensiveUserImpact(
			new UserIdentityValue( 0, '' ),
			0,
			[],
			[],
			new UserTimeCorrection( 'System|0' ),
			0,
			0,
			[],
			[],
			new EditingStreak()
		);
	}

	/** @inheritDoc */
	protected function loadFromJsonArray( array $json ): void {
		parent::loadFromJsonArray( $json );

		Assert::parameterKeyType( 'string', $json['dailyTotalViews'], '$json[\'dailyTotalViews\']' );
		Assert::parameterElementType( 'integer', $json['dailyTotalViews'], '$json[\'dailyTotalViews\']' );
		Assert::parameterElementType( 'array', $json['dailyArticleViews'], '$json[\'dailyArticleViews\']' );
		foreach ( $json['dailyArticleViews'] as $title => $views ) {
			Assert::parameterKeyType( 'string', $views, '$json[\'dailyArticleViews\'][\'' . $title . '\']' );
		}

		$this->dailyTotalViews = $json['dailyTotalViews'];
		$this->dailyArticleViews = $json['dailyArticleViews'];
	}

	/** @inheritDoc */
	public function jsonSerialize(): array {
		return parent::jsonSerialize() + [
			'dailyTotalViews' => $this->dailyTotalViews,
			'dailyArticleViews' => $this->dailyArticleViews,
		];
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function isPageViewDataStale(): bool {
		$latestPageViewsDateTime = new DateTime( array_key_last( $this->getDailyTotalViews() ) );
		$now = MWTimestamp::getInstance();
		$diff = $now->timestamp->diff( $latestPageViewsDateTime );
		// Page view data generation can lag by 24-48 hours.
		// Consider the data stale if it's from before (UTC) yesterday.
		return $diff->days > 1;
	}

}
