<?php
namespace GrowthExperiments\NewcomerTasks;

use MediaWiki\Linker\LinkTarget;

class ImproveToneRecommendation implements Recommendation {
	private LinkTarget $title;
	private array $toneData;

	public function __construct( LinkTarget $title, array $toneData ) {
		$this->title = $title;
		$this->toneData = $toneData;
	}

	public function getToneData(): array {
		return $this->toneData;
	}

	public function getTitle(): LinkTarget {
		return $this->title;
	}

	public function toArray(): array {
		return [
			'title' => $this->title->getText(),
			'toneData' => $this->toneData,
		];
	}
}
