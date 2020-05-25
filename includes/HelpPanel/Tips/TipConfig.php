<?php

namespace GrowthExperiments\HelpPanel\Tips;

class TipConfig {
	/**
	 * @var string
	 */
	private $msgKey;
	/**
	 * @var string
	 */
	private $learnMoreTitle;
	/**
	 * @var string
	 */
	private $skinName;
	/**
	 * @var string
	 */
	private $taskTypeId;
	/**
	 * @var array
	 */
	private $extraConfig;
	/**
	 * @var string
	 */
	private $tipTypeId;

	/**
	 * Provide various pieces of configuration used in rendering a tip.
	 *
	 * @param string $tipTypeId
	 *   One of the values defined in TipsBuilder::TIP_TYPES
	 * @param string $msgKey
	 * @param string $learnMoreTitle
	 * @param string $skinName
	 * @param string $taskTypeId
	 * @param array $extraConfig
	 *   Additional values needed to render the tip, for example, the title for
	 *   the "learn more" link
	 */
	public function __construct(
		string $tipTypeId,
		string $msgKey,
		string $learnMoreTitle,
		string $skinName,
		string $taskTypeId,
		array $extraConfig = []
	) {
		$this->tipTypeId = $tipTypeId;
		$this->msgKey = $msgKey;
		$this->learnMoreTitle = $learnMoreTitle;
		$this->skinName = $skinName;
		$this->taskTypeId = $taskTypeId;
		$this->extraConfig = $extraConfig;
	}

	/**
	 * @return string
	 */
	public function getMessageKey() :string {
		return $this->msgKey;
	}

	/**
	 * @return string
	 */
	public function getLearnMoreTitle(): string {
		return $this->learnMoreTitle;
	}

	/**
	 * @return array
	 */
	public function getExtraConfig(): array {
		return $this->extraConfig;
	}

	/**
	 * @return string
	 */
	public function getSkinName(): string {
		return $this->skinName;
	}

	/**
	 * @return string
	 */
	public function getTaskTypeId(): string {
		return $this->taskTypeId;
	}

	/**
	 * @return string
	 */
	public function getTipTypeId() :string {
		return $this->tipTypeId;
	}

}
