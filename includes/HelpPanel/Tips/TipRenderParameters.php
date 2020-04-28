<?php

namespace GrowthExperiments\HelpPanel\Tips;

class TipRenderParameters {
	/**
	 * @var string
	 */
	private $messageKey;
	/**
	 * @var array
	 */
	private $parameters;

	/**
	 * @param string $messageKey
	 * @param array $parameters
	 */
	public function __construct( string $messageKey, array $parameters = [] ) {
		$this->messageKey = $messageKey;
		$this->parameters = $parameters;
	}

	/**
	 * @return string
	 */
	public function getMessageKey() :string {
		return $this->messageKey;
	}

	/**
	 * @return array
	 */
	public function getExtraParameters() :array {
		return $this->parameters;
	}

}
