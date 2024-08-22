<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\HelpPanel\QuestionFormatter;
use GrowthExperiments\HelpPanel\QuestionRecord;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;

class RecentQuestionsFormatter {

	/**
	 * @var QuestionRecord[]
	 */
	private $questionRecords;
	/**
	 * @var IContextSource
	 */
	private $contextSource;
	/** @var string */
	private $dataLinkIdKey;
	/** @var string */
	private $recentQuestionsCssClass;

	/**
	 * @param IContextSource $contextSource
	 * @param array $questionRecords
	 * @param string $dataLinkIdKey
	 */
	public function __construct(
		IContextSource $contextSource,
		array $questionRecords,
		$dataLinkIdKey
	) {
		$this->questionRecords = $questionRecords;
		$this->contextSource = $contextSource;
		$this->dataLinkIdKey = $dataLinkIdKey;
		$this->recentQuestionsCssClass = 'recent-questions-' . $this->dataLinkIdKey;
	}

	public function formatHeader() {
		return Html::element( 'h3', [], $this->contextSource
			->msg( 'growthexperiments-homepage-recent-questions-header' )
			->params( $this->contextSource->getUser() )
			->text()
		);
	}

	public function formatResponses() {
		$html = Html::openElement(
			'div',
			[ 'class' => $this->recentQuestionsCssClass . '-list' ]
		);
		$html .= Html::openElement( 'ul' );
		$count = 1;
		foreach ( $this->questionRecords as $questionRecord ) {
			$dataLinkId = $questionRecord->isArchived() ?
				$this->dataLinkIdKey . '-archived-' . $count :
				$this->dataLinkIdKey . '-' . $count;
			$questionFormatter = new QuestionFormatter(
				$this->contextSource,
				$questionRecord,
				$dataLinkId,
				'growthexperiments-homepage-recent-questions-posted-on',
				'growthexperiments-homepage-recent-questions-archived',
				'growthexperiments-homepage-recent-questions-archived-tooltip'
			);
			$html .= Html::rawElement( 'li', [], $questionFormatter->format() );
			$count++;
		}
		$html .= Html::closeElement( 'ul' );
		$html .= Html::closeElement( 'div' );
		return $html;
	}

	public function format() {
		return Html::rawElement( 'div', [ 'class' => $this->recentQuestionsCssClass ],
			$this->questionRecords ? $this->formatHeader() . $this->formatResponses() : ''
		);
	}

}
