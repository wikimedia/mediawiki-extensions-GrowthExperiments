<?php
namespace GrowthExperiments\NewcomerTasks\ReviseTone;

use GrowthExperiments\NewcomerTasks\Recommendation;
use MediaWiki\Linker\LinkTarget;

class ReviseToneRecommendation implements Recommendation {

	public function __construct(
		private readonly LinkTarget $title,
		private readonly string $paragraphText
	) {
	}

	public function getTitle(): LinkTarget {
		return $this->title;
	}

	public function toArray(): array {
		return [
			'title' => $this->title->getText(),
			'paragraphText' => $this->paragraphText,
		];
	}
}
