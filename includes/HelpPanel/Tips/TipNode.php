<?php

namespace GrowthExperiments\HelpPanel\Tips;

/**
 * Value object for containing data about a tip node, which roughly corresponds
 * to an HTML node.
 */
class TipNode {

	private string $type;
	private array $data;
	private string $messageKey;

	public function __construct(
		string $type, string $messageKey, array $data = [] ) {
		$this->type = $type;
		$this->data = $data;
		$this->messageKey = $messageKey;
	}

	/**
	 * @return array
	 */
	public function getData(): array {
		return $this->data;
	}

	/**
	 * Get the message key if defined.
	 *
	 * @return string
	 */
	public function getMessageKey(): string {
		return $this->messageKey;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}
}
