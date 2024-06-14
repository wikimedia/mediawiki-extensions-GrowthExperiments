<?php

namespace GrowthExperiments\HelpPanel\Tips;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use MediaWiki\Context\IContextSource;
use MessageLocalizer;

/**
 * Assemble tips in HTML for display in suggested edits panel.
 */
class TipsAssembler {

	/**
	 * @var IContextSource
	 */
	private $messageLocalizer;
	/**
	 * @var ConfigurationLoader
	 */
	private $configurationLoader;

	/**
	 * @var TipNodeRenderer
	 */
	private $tipNodeRenderer;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param TipNodeRenderer $tipNodeRenderer
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader, TipNodeRenderer $tipNodeRenderer
	) {
		$this->configurationLoader = $configurationLoader;
		$this->tipNodeRenderer = $tipNodeRenderer;
	}

	/**
	 * @param MessageLocalizer $messageLocalizer
	 */
	public function setMessageLocalizer(
		MessageLocalizer $messageLocalizer
	): void {
		$this->messageLocalizer = $messageLocalizer;
		$this->tipNodeRenderer->setMessageLocalizer( $messageLocalizer );
	}

	/**
	 * Get an array of rendered HTML.
	 *
	 * Each index in the array corresponds to a tab in the suggested edits
	 * guidance screen in the help panel.
	 *
	 * Obtain a TipLoader, load the tips, and pass each tip node through the
	 * renderer.
	 *
	 * @param string $skinName
	 * @param string $editor
	 * @param TaskType[] $taskTypes
	 * @param string $taskTypeId
	 * @param string $dir
	 * @return array
	 */
	public function getTips(
		string $skinName, string $editor, array $taskTypes, string $taskTypeId, string $dir
	): array {
		$tipLoader = new TipLoader( $this->configurationLoader, $this->messageLocalizer );
		return array_values( array_map( function ( $tipNodes ) use ( $skinName, $dir ) {
			return $this->tipNodeRenderer->render( $tipNodes, $skinName, $dir );
		}, $tipLoader->loadTipNodes( $skinName, $editor, $taskTypes, $taskTypeId ) ) );
	}

}
