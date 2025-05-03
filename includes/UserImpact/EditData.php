<?php

namespace GrowthExperiments\UserImpact;

/**
 * Value object for containing edit data related to a user's impact.
 */
class EditData {
	private array $editCountByNamespace;
	private array $editCountByDay;
	private array $editCountByTaskType;
	private int $revertedEditCount;
	private int $newcomerTaskEditCount;
	private ?string $lastEditTimestamp;
	private array $editedArticles;
	private int $createdArticlesCount;

	/**
	 * @param int[] $editCountByNamespace Number of edits made by the user per namespace ID.
	 * @param int[] $editCountByDay Number of article-space edits made by the user
	 *   by day. The format matches {@see UserImpact::getEditCountByDay()}.
	 * @param array<string,int> $editCountByTaskType Number of newcomer task edits per task type
	 *  {@see UserImpact::getEditCountByTaskType()}.
	 * @param int $revertedEditCount Number of edits by the user that got reverted (determined by
	 * the mw-reverted tag).
	 * @param int $newcomerTaskEditCount Number of edits with "newcomer task" tag (suggested edits).
	 * @param string|null $lastEditTimestamp MW_TS date of last article-space edit.
	 * @param array[] $editedArticles List of article-space titles the user has edited, sorted from
	 *   most recently edited to least recently edited. Keyed by article title (in dbkey format),
	 *   the value is an array with 'oldestEdit' and 'newestEdit' fields, each with an MW_TS date.
	 * @param int $createdArticlesCount Count of article-space titles the user has created
	 */
	public function __construct(
		array $editCountByNamespace,
		array $editCountByDay,
		array $editCountByTaskType,
		int $revertedEditCount,
		int $newcomerTaskEditCount,
		?string $lastEditTimestamp,
		array $editedArticles,
		int $createdArticlesCount = 0
	) {
		$this->editCountByNamespace = $editCountByNamespace;
		$this->editCountByDay = $editCountByDay;
		$this->revertedEditCount = $revertedEditCount;
		$this->newcomerTaskEditCount = $newcomerTaskEditCount;
		$this->lastEditTimestamp = $lastEditTimestamp;
		$this->editedArticles = $editedArticles;
		$this->editCountByTaskType = $editCountByTaskType;
		$this->createdArticlesCount = $createdArticlesCount;
	}

	/**
	 * Number of edits made by the user per namespace.
	 * @return int[] Namespace ID => edit count.
	 */
	public function getEditCountByNamespace(): array {
		return $this->editCountByNamespace;
	}

	/**
	 * Number of article-space edits made by the user by day.
	 * Days are interpreted according to the wiki's timezone default offset.
	 * @return int[] Same as UserImpact::getEditCountByDay().
	 * @see $wgLocalTZoffset
	 */
	public function getEditCountByDay(): array {
		return $this->editCountByDay;
	}

	/**
	 * Number of total edits by the user that got reverted (determined
	 * by the mw-reverted tag).
	 */
	public function getRevertedEditCount(): int {
		return $this->revertedEditCount;
	}

	/**
	 * Number of edits with "newcomer task" tag (suggested edits).
	 */
	public function getNewcomerTaskEditCount(): int {
		return $this->newcomerTaskEditCount;
	}

	/**
	 * Number of newcomer task edits, grouped by task type.
	 *
	 * @return array<string,int>
	 */
	public function getEditCountByTaskType(): array {
		return $this->editCountByTaskType;
	}

	/**
	 * MW_TS date of last article-space edit.
	 * @return string|null
	 */
	public function getLastEditTimestamp(): ?string {
		return $this->lastEditTimestamp;
	}

	/**
	 * List of article-space titles the user has edited, sorted from most recently edited
	 * to least recently edited. Keyed by article title (in dbkey format), the value is an
	 * array with 'oldestEdit' and 'newestEdit' fields, each with an MW_TS date.
	 * @return array[]
	 */
	public function getEditedArticles(): array {
		return $this->editedArticles;
	}

	/**
	 * Count of article-space titles the user has created.
	 *
	 * @return int
	 */
	public function getCreatedArticlesCount(): int {
		return $this->createdArticlesCount;
	}

}
