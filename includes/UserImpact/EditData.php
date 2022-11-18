<?php

namespace GrowthExperiments\UserImpact;

use MediaWiki\User\UserTimeCorrection;

/**
 * Value object for containing edit data related to a user's impact.
 */
class EditData {
	private array $editCountByNamespace;
	private array $editCountByDay;
	private int $newcomerTaskEditCount;
	private ?string $lastEditTimestamp;
	private array $editedArticles;
	private UserTimeCorrection $userTimeCorrection;

	/**
	 * @param array $editCountByNamespace number of edits made by the user per namespace
	 * @param array $editCountByDay number of article-space edits made by the user
	 *   by day. The format matches UserImpact::getEditCountByDay().
	 * @param int $newcomerTaskEditCount number of edits with "newcomer task" tag (suggested edits)
	 * @param string|null $lastEditTimestamp MW_TS date of last article-space edit
	 * @param array $editedArticles list of article-space titles the user has edited, sorted from
	 *   most recently edited to least recently edited. The article title is the key, the oldest edit timestamp
	 *   from the user is the value.
	 * @param UserTimeCorrection $userTimeCorrection the timezone used for defining what "day" means
	 *   in editCountByDay, based on the user's timezone preference.
	 */
	public function __construct(
		array $editCountByNamespace,
		array $editCountByDay,
		int $newcomerTaskEditCount,
		?string $lastEditTimestamp,
		array $editedArticles,
		UserTimeCorrection $userTimeCorrection
	) {
		$this->editCountByNamespace = $editCountByNamespace;
		$this->editCountByDay = $editCountByDay;
		$this->newcomerTaskEditCount = $newcomerTaskEditCount;
		$this->lastEditTimestamp = $lastEditTimestamp;
		$this->editedArticles = $editedArticles;
		$this->userTimeCorrection = $userTimeCorrection;
	}

	/**
	 * @return array
	 */
	public function getEditCountByNamespace(): array {
		return $this->editCountByNamespace;
	}

	/**
	 * @return array
	 */
	public function getEditCountByDay(): array {
		return $this->editCountByDay;
	}

	/**
	 * @return int
	 */
	public function getNewcomerTaskEditCount(): int {
		return $this->newcomerTaskEditCount;
	}

	/**
	 * @return string|null
	 */
	public function getLastEditTimestamp(): ?string {
		return $this->lastEditTimestamp;
	}

	/**
	 * @return array
	 */
	public function getEditedArticles(): array {
		return $this->editedArticles;
	}

	/**
	 * @return UserTimeCorrection
	 */
	public function getUserTimeCorrection(): UserTimeCorrection {
		return $this->userTimeCorrection;
	}

}
