<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

/**
 * Represents an individual suggested link within a LinkRecommendation.
 */
class LinkRecommendationLink {

	/** @var string */
	private $linkText;
	/** @var string */
	private $linkTarget;
	/** @var int */
	private $matchIndex;
	/** @var int */
	private $wikitextOffset;
	/** @var float */
	private $score;
	/** @var string */
	private $contextBefore;
	/** @var string */
	private $contextAfter;
	/** @var int */
	private $linkIndex;

	/**
	 * @param string $linkText The text fragment which would be linked (as plaintext).
	 *   This text is present and unlinked in the article revision that was used for generating
	 *   recommendations.
	 * @param string $linkTarget The title to link to, in any format that can be parsed by
	 *   TitleParser.
	 * @param int $matchIndex The 0-based index the link text within all matches of $text
	 *   in the article.
	 * @param int $wikitextOffset The 0-based index of the first character of the link text in the
	 *   wikitext, in Unicode characters.
	 * @param float $score The confidence score of the recommended link (a number between 0
	 *   and 1).
	 * @param string $contextBefore A few characters of text from the artcile right before the
	 *   text to be linked. Might be omitted (set to empty string) if the recommended link is
	 *   preceded by something that cannot easily be converted to plaintext (such as a template).
	 * @param string $contextAfter Like $contextBefore but the text is right after the link text.
	 * @param int $linkIndex 0-based position in the list of recommendations (in the order
	 *   they occur in the article).
	 */
	public function __construct(
		string $linkText,
		string $linkTarget,
		int $matchIndex,
		int $wikitextOffset,
		float $score,
		string $contextBefore,
		string $contextAfter,
		int $linkIndex
	) {
		$this->linkText = $linkText;
		$this->linkTarget = $linkTarget;
		$this->matchIndex = $matchIndex;
		$this->wikitextOffset = $wikitextOffset;
		$this->score = $score;
		$this->contextBefore = $contextBefore;
		$this->contextAfter = $contextAfter;
		$this->linkIndex = $linkIndex;
	}

	/**
	 * The text fragment which would be linked (as plaintext). This text is present and unlinked
	 * in the article revision that was used for generating recommendations.
	 */
	public function getText(): string {
		return $this->linkText;
	}

	/**
	 * The title to link to, in any format that can be parsed by TitleParser.
	 */
	public function getLinkTarget(): string {
		return $this->linkTarget;
	}

	/**
	 * The 0-based index the link text within all matches of $text within the simple wikitext
	 * of the (top-level wikitext that's not part of any kind of wikitext construct). This is
	 * roughly equivalent to the match index in the text of top-level (within a `<section>`)
	 * `<p>` nodes in Parsoid HTML.
	 */
	public function getMatchIndex(): int {
		return $this->matchIndex;
	}

	/**
	 * The 0-based index of the first character of the link text in the wikitext,
	 * in Unicode characters.
	 */
	public function getWikitextOffset(): int {
		return $this->wikitextOffset;
	}

	/**
	 * The confidence score of the recommended link (a number between 0 and 1).
	 */
	public function getScore(): float {
		return $this->score;
	}

	/**
	 * A few characters of text from the article right before the text to be linked. Might
	 * be omitted (set to empty string) if the recommended link is preceded by something
	 * that cannot easily be converted to plaintext (such as a template).
	 */
	public function getContextBefore(): string {
		return $this->contextBefore;
	}

	/**
	 * A few characters of text from the article right after the text to be linked. Might
	 * be omitted (set to empty string) if the recommended link is followed by something
	 * that cannot easily be converted to plaintext (such as a template).
	 */
	public function getContextAfter(): string {
		return $this->contextAfter;
	}

	/**
	 * 0-based position in the list of recommendations (in the order they occur in the article).
	 */
	public function getLinkIndex(): int {
		return $this->linkIndex;
	}

	public function toArray(): array {
		return [
			'link_text' => $this->linkText,
			'link_target' => $this->linkTarget,
			'match_index' => $this->matchIndex,
			'wikitext_offset' => $this->wikitextOffset,
			'score' => $this->score,
			'context_before' => $this->contextBefore,
			'context_after' => $this->contextAfter,
			'link_index' => $this->linkIndex,
		];
	}

}
