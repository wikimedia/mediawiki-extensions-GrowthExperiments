<?php

namespace GrowthExperiments\HelpPanel\Tips;

use GrowthExperiments\NewcomerTasks\ConfigurationLoader\ConfigurationLoader;
use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use LogicException;
use MessageLocalizer;

class TipLoader {

	/**
	 * @var ConfigurationLoader
	 */
	private $configurationLoader;

	private const DEFAULT_EDITOR = 'visualeditor';

	private const DEFAULT_SKIN = 'vector';
	/**
	 * @var MessageLocalizer
	 */
	private $messageLocalizer;

	/**
	 * @param ConfigurationLoader $configurationLoader
	 * @param MessageLocalizer $messageLocalizer
	 */
	public function __construct(
		ConfigurationLoader $configurationLoader, MessageLocalizer $messageLocalizer
	) {
		$this->configurationLoader = $configurationLoader;
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Get an array of TipNode objects, each of which contain the step name
	 * and a TipNode tree to render.
	 *
	 * @param string $skinName
	 * @param string $editor
	 * @param TaskType $taskType
	 * @return array
	 */
	public function loadTipNodes(
		string $skinName, string $editor, TaskType $taskType ): array {
		$tipTree = $this->getTipTreeForTaskType( $taskType );
		return array_filter( array_map( function ( $stepName ) use (
			$skinName, $editor, $tipTree, $taskType
		) {
			return $this->getTipNodesForStep(
				$stepName,
				$skinName,
				$editor,
				$taskType->getId(),
				$tipTree
			);
		}, $tipTree->getStepNames() ) );
	}

	/**
	 * @param TaskType $taskType
	 * @throws LogicException
	 * @return TipTree
	 */
	private function getTipTreeForTaskType( TaskType $taskType ): TipTree {
		switch ( $taskType->getId() ) {
			case 'copyedit':
				return new CopyeditTipTree( $taskType->getLearnMoreLink() );
			case 'links':
				return new LinkTipTree( $taskType->getLearnMoreLink() );
			case 'update':
				return new UpdateTipTree( $taskType->getLearnMoreLink() );
			case 'references':
				return new ReferencesTipTree( $taskType->getLearnMoreLink() );
			case 'expand':
				return new ExpandTipTree( $taskType->getLearnMoreLink() );
			default:
				throw new LogicException( $taskType->getId() . ' does not have tip steps defined.' );
		}
	}

	/**
	 * Get an array of TipNodes for a single step.
	 *
	 * @param string $stepName
	 * @param string $skinName
	 * @param string $editor
	 * @param string $taskTypeId
	 * @param TipTree $tipSteps
	 * @return TipNode[]
	 */
	private function getTipNodesForStep(
		string $stepName, string $skinName, string $editor, string $taskTypeId, TipTree $tipSteps
	): array {
		return array_filter( array_map( function ( $tipTypeId ) use (
			$tipSteps, $stepName, $editor, $taskTypeId, $skinName
		) {
			$steps = $tipSteps->getTree();
			if ( !isset( $steps[$stepName][$tipTypeId] ) ) {
				return null;
			}
			$messageKey = $this->getMessageKeyWithFallback(
				$skinName,
				$editor,
				$taskTypeId,
				$tipTypeId,
				$stepName
			);
			if ( !$messageKey ) {
				return null;
			}
			return new TipNode( $tipTypeId, $messageKey, $steps[$stepName][$tipTypeId] ?? [] );
		}, $tipSteps->getTipTypes() ) );
	}

	/**
	 * Get a Message for a particular skin, editor, task type, tip type, and step.
	 *
	 * If the message is disabled or doesn't exist, check variants in the
	 * following order:
	 * - Default editor with requested skin
	 * - Default skin with requested editor
	 * - Default skin and default editor
	 *
	 * @param string $skinName
	 * @param string $editor
	 * @param string $taskTypeId
	 * @param string $tipTypeId
	 * @param string $step
	 * @return string
	 */
	private function getMessageKeyWithFallback(
		string $skinName,
		string $editor,
		string $taskTypeId,
		string $tipTypeId,
		string $step
	): string {
		$msg = $this->messageLocalizer->msg( $this->buildMessageKey(
			$skinName, $editor, $taskTypeId, $tipTypeId, $step )
		);
		if ( $msg->isDisabled() ) {
			$msg = $this->messageLocalizer->msg(
				$this->buildMessageKey( $skinName, self::DEFAULT_EDITOR, $taskTypeId, $tipTypeId, $step )
			);
		}
		if ( $msg->isDisabled() ) {
			$msg = $this->messageLocalizer->msg(
				$this->buildMessageKey( self::DEFAULT_SKIN, $editor, $taskTypeId, $tipTypeId, $step )
			);
		}
		if ( $msg->isDisabled() ) {
			$msg = $this->messageLocalizer->msg(
				$this->buildMessageKey(
					self::DEFAULT_SKIN,
					self::DEFAULT_EDITOR,
					$taskTypeId,
					$tipTypeId,
					$step
				)
			);
		}
		return $msg->isDisabled() ? '' : $msg->getKey();
	}

	/**
	 * @param string $skinName
	 * @param string $editor
	 * @param string $taskTypeId
	 * @param string $tipTypeId
	 * @param string $step
	 * @return string
	 */
	private function buildMessageKey(
		string $skinName, string $editor, string $taskTypeId, string $tipTypeId, string $step
	): string {
		return sprintf(
			'%s-%s-%s-%s-%s-%s',
			'growthexperiments-help-panel-suggestededits-tips',
			$skinName,
			$editor,
			$taskTypeId,
			$tipTypeId,
			$step
		);
	}

}
