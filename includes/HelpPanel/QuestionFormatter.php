<?php

declare( strict_types = 1 );

namespace GrowthExperiments\HelpPanel;

use GrowthExperiments\Util;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

class QuestionFormatter {

	private IContextSource $contextSource;
	private string $dataLinkId;
	private string $postedOnMsgKey;
	private string $archivedMsgKey;
	private string $archivedTooltipMsgKey;
	private QuestionRecord $questionRecord;

	public function __construct(
		IContextSource $contextSource,
		QuestionRecord $questionRecord,
		string $dataLinkId,
		string $postedOnMsgKey,
		string $archivedMsgKey,
		string $archivedTooltipMsgKey
	) {
		$this->contextSource = $contextSource;
		$this->dataLinkId = $dataLinkId;
		$this->postedOnMsgKey = $postedOnMsgKey;
		$this->archivedMsgKey = $archivedMsgKey;
		$this->questionRecord = $questionRecord;
		$this->archivedTooltipMsgKey = $archivedTooltipMsgKey;
	}

	public function format(): string {
		return $this->questionRecord->isArchived() ? $this->formatArchived() : $this->formatUnarchived();
	}

	private function formatUnarchived(): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'question-link-wrapper' ],
			Html::element(
				'a',
				[
					'class' => 'question-text',
					'href' => $this->questionRecord->getResultUrl(),
					'data-link-id' => $this->dataLinkId,
				],
				$this->questionRecord->getQuestionText()
			)
		) .	$this->getPostedOnHtml();
	}

	private function formatArchived(): string {
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
							'title' => $this->contextSource->msg( $this->archivedTooltipMsgKey )->text(),
						],
						$this->contextSource->msg( $this->archivedMsgKey )->text()
					) .
					')'
			) .
			$this->getPostedOnHtml();
	}

	private function getPostedOnHtml(): string {
		return Html::element(
			'span',
			[ 'class' => 'question-posted-on' ],
			$this->contextSource->msg( $this->postedOnMsgKey, $this->getRelativeTime() )->text()
		);
	}

	private function getRelativeTime(): string {
		$elapsedTime = (int)wfTimestamp() - (int)wfTimestamp(
			TS_UNIX,
			$this->questionRecord->getTimestamp()
		);
		return Util::getRelativeTime( $this->contextSource, $elapsedTime );
	}
}
