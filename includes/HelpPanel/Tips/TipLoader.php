<?php

namespace GrowthExperiments\HelpPanel\Tips;

use GrowthExperiments\NewcomerTasks\TaskType\TaskType;
use LogicException;
use MessageLocalizer;

class TipLoader {

	private const DEFAULT_EDITOR = 'visualeditor';

	private const DEFAULT_SKIN = 'vector';
	private MessageLocalizer $messageLocalizer;

	public function __construct(
		MessageLocalizer $messageLocalizer
	) {
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Get an array of TipNode objects, each of which contain the step name
	 * and a TipNode tree to render.
	 *
	 * @param string $skinName
	 * @param string $editor
	 * @param TaskType[] $taskTypes
	 * @param string $taskTypeId
	 * @return array
	 */
	public function loadTipNodes(
		string $skinName, string $editor, array $taskTypes, string $taskTypeId ): array {
		$tipTree = $this->getTipTreeForTaskType(
			$taskTypeId,
			$this->buildExtraData( $taskTypes )
		);
		return array_filter( array_map( function ( $stepName ) use (
			$skinName, $editor, $tipTree, $taskTypeId
		) {
			return $this->getTipNodesForStep(
				$stepName,
				$skinName,
				$editor,
				$taskTypeId,
				$tipTree
			);
		}, $tipTree->getStepNames() ) );
	}

	private function buildExtraData( array $taskTypes ): array {
		return array_map( static function ( TaskType $taskType ) {
			return [ 'learnMoreLink' => $taskType->getLearnMoreLink() ];
		}, $taskTypes );
	}

	/**
	 * @param string $taskTypeId
	 * @param array $extraData
	 * @throws LogicException
	 * @return TipTree
	 */
	private function getTipTreeForTaskType( string $taskTypeId, array $extraData ): TipTree {
		switch ( $taskTypeId ) {
			case 'copyedit':
				return new CopyeditTipTree( $extraData );
			case 'image-recommendation':
				return new ImageRecommendationTipTree( $extraData );
			case 'section-image-recommendation':
				return new SectionImageRecommendationTipTree( $extraData );
			case 'links':
				return new LinkTipTree( $extraData );
			case 'link-recommendation':
				return new LinkRecommendationTipTree( $extraData );
			case 'update':
				return new UpdateTipTree( $extraData );
			case 'references':
				return new ReferencesTipTree( $extraData );
			case 'expand':
				return new ExpandTipTree( $extraData );
			default:
				throw new LogicException( $taskTypeId . ' does not have tip steps defined.' );
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
		$nodesPerTip = array_filter( array_map( function ( $tipTypeId ) use (
			$tipSteps, $stepName, $editor, $taskTypeId, $skinName
		) {
			$messageKey = null;
			$steps = $tipSteps->getTree();
			if ( !isset( $steps[$stepName][$tipTypeId] ) ) {
				return null;
			}
			if ( $tipTypeId === "main-multiple" ) {
				$nodes = [];
				$i = 1;
				while ( $messageKey !== "" && $i <= TipTree::TIP_TYPE_MAIN_MULTIPLE_MAX_NODES ) {
					$messageKey = $this->getMessageKeyWithFallback(
						$skinName,
						$editor,
						$taskTypeId,
						$tipTypeId . "-" . $i,
						$stepName
					);
					if ( $messageKey ) {
						$nodes[] = new TipNode( $tipTypeId, $messageKey, $steps[$stepName][$tipTypeId] ?? [] );
					}
					$i++;
				}
				return $nodes;
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
			return [ new TipNode( $tipTypeId, $messageKey, $steps[$stepName][$tipTypeId] ?? [] ) ];
		}, $tipSteps->getTipTypes() ) );

		return array_merge( ...array_values( $nodesPerTip ) );
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
