<?php

namespace GrowthExperiments\HelpPanel;

use GrowthExperiments\Util;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

class QuestionFormatter {

	/**
	 * @var IContextSource
	 */
	private $contextSource;
	/** @var string */
	private $dataLinkId;
	/** @var string */
	private $postedOnMsgKey;
	/** @var string */
	private $archivedMsgKey;
	/** @var string */
	private $archivedTooltipMsgKey;
	/**
	 * @var QuestionRecord
	 */
	private $questionRecord;

	/**
	 * @param IContextSource $contextSource
	 * @param QuestionRecord $questionRecord
	 * @param string $dataLinkId
	 * @param string $postedOnMsgKey
	 * @param string $archivedMsgKey
	 * @param string $archivedTooltipMsgKey
	 */
	public function __construct(
		IContextSource $contextSource,
		QuestionRecord $questionRecord,
		$dataLinkId,
		$postedOnMsgKey,
		$archivedMsgKey,
		$archivedTooltipMsgKey
	) {
		$this->contextSource = $contextSource;
		$this->dataLinkId = $dataLinkId;
		$this->postedOnMsgKey = $postedOnMsgKey;
		$this->archivedMsgKey = $archivedMsgKey;
		$this->questionRecord = $questionRecord;
		$this->archivedTooltipMsgKey = $archivedTooltipMsgKey;
	}

	/**
	 * @return string
	 */
	public function format() {
		return $this->questionRecord->isArchived() ? $this->formatArchived() : $this->formatUnarchived();
	}

	private function formatUnarchived() {
		return Html::rawElement(
			'div',
			[ 'class' => 'question-link-wrapper' ],
			Html::element(
				'a',
				[
					'class' => 'question-text',
					'href' => $this->questionRecord->getResultUrl(),
					'data-link-id' => $this->dataLinkId
				],
				$this->questionRecord->getQuestionText()
			)
		) .	$this->getPostedOnHtml();
	}

	private function formatArchived() {
		return Html::rawElement(
				'div',
				[ 'class' => 'question-link-wrapper question-archived' ],
				Html::element(
						'span',
						[ 'class' => 'question-text' ],
						$this->questionRecord->getQuestionText()
					) .
					' (' .
					Html::element(
						'a',
						[
							'href' => $this->questionRecord->getArchiveUrl(),
							'data-link-id' => $this->dataLinkId,
							'title' => $this->contextSource->msg( $this->archivedTooltipMsgKey )->text()
						],
						$this->contextSource->msg( $this->archivedMsgKey )->text()
					) .
					')'
			) .
			$this->getPostedOnHtml();
	}

	private function getPostedOnHtml() {
		return Html::element(
				'span',
				[ 'class' => 'question-posted-on' ],
				$this->contextSource
					->msg( $this->postedOnMsgKey )
					->params( $this->getRelativeTime() )
					->text()
		);
	}

	private function getRelativeTime() {
		$elapsedTime = (int)wfTimestamp() - (int)wfTimestamp(
			TS_UNIX,
			$this->questionRecord->getTimestamp()
		);
		return Util::getRelativeTime( $this->contextSource, $elapsedTime );
	}
}
