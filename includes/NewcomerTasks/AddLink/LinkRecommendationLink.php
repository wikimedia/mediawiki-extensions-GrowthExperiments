<?php

namespace GrowthExperiments\NewcomerTasks\AddLink;

/**
 * Represents an individual suggested link within a LinkRecommendation.
 */
class LinkRecommendationLink {

	/** @var string */
	private $phraseToLink;
	/** @var string */
	private $linkTarget;
	/** @var int */
	private $instanceOccurrence;
	/** @var float */
	private $probability;
	/** @var string */
	private $contextBefore;
	/** @var string */
	private $contextAfter;
	/** @var int */
	private $insertionOrder;

	/**
	 * @param string $phraseToLink The text fragment which would be linked (as plaintext).
	 *   This text is present and unlinked in the article revision that was used for generating
	 *   recommendations.
	 * @param string $linkTarget The title to link to, in any format that can be parsed by
	 *   TitleParser.
	 * @param int $instanceOccurrence The 1-based index the link text within all matches of $text
	 *   in the article (calculated after removing all templates / extensions tags / parserfunctions
	 *   and converting the article to plaintext).
	 * @param float $probability The confidence score of the recommended link (a number between 0
	 *   and 1).
	 * @param string $contextBefore A few characters of text from the artcile right before the
	 *   text to be linked. Might be omitted (set to empty string) if the recommended link is
	 *   preceded by something that cannot easily be converted to plaintext (such as a template).
	 * @param string $contextAfter Like $contextBefore but the text is right after the link text.
	 * @param int $insertionOrder 1-based position in the list of recommendations (in the order
	 *   they occur in the article).
	 */
	public function __construct(
		string $phraseToLink,
		string $linkTarget,
		int $instanceOccurrence,
		float $probability,
		string $contextBefore,
		string $contextAfter,
		int $insertionOrder
	) {
		$this->phraseToLink = $phraseToLink;
		$this->linkTarget = $linkTarget;
		$this->instanceOccurrence = $instanceOccurrence;
		$this->probability = $probability;
		$this->contextBefore = $contextBefore;
		$this->contextAfter = $contextAfter;
		$this->insertionOrder = $insertionOrder;
	}

	/**
	 * The text fragment which would be linked (as plaintext). This text is present and unlinked
	 * in the article revision that was used for generating recommendations.
	 * @return string
	 */
	public function getText(): string {
		return $this->phraseToLink;
	}

	/**
	 * The title to link to, in any format that can be parsed by TitleParser.
	 * @return string
	 */
	public function getLinkTarget(): string {
		return $this->linkTarget;
	}

	/**
	 * The 1-based index the link text within all matches of $text in the article (calculated after
	 * removing all templates / extensions tags / parserfunctions and converting the article to
	 * plaintext).
	 * @return int
	 */
	public function getInstanceOccurrence(): int {
		return $this->instanceOccurrence;
	}

	/**
	 * The confidence score of the recommended link (a number between 0 and 1).
	 * @return float
	 */
	public function getProbability(): float {
		return $this->probability;
	}

	/**
	 * A few characters of text from the artcile right before the text to be linked. Might
	 * be omitted (set to empty string) if the recommended link is preceded by something
	 * that cannot easily be converted to plaintext (such as a template).
	 * @return string
	 */
	public function getContextBefore(): string {
		return $this->contextBefore;
	}

	/**
	 * A few characters of text from the artcile right after the text to be linked. Might
	 * be omitted (set to empty string) if the recommended link is followed by something
	 * that cannot easily be converted to plaintext (such as a template).
	 * @return string
	 */
	public function getContextAfter(): string {
		return $this->contextAfter;
	}

	/**
	 * 1-based position in the list of recommendations (in the order they occur in the article).
	 * @return int
	 */
	public function getInsertionOrder(): int {
		return $this->insertionOrder;
	}

	/**
	 * @return array
	 */
	public function toArray(): array {
		return [
			'phrase_to_link' => $this->phraseToLink,
			'link_target' => $this->linkTarget,
			'instance_occurrence' => $this->instanceOccurrence,
			'probability' => $this->probability,
			'context_before' => $this->contextBefore,
			'context_after' => $this->contextAfter,
			'insertion_order' => $this->insertionOrder,
		];
	}

}
