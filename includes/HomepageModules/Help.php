<?php

namespace GrowthExperiments\HomepageModules;

use GrowthExperiments\HelpPanel;
use GrowthExperiments\HelpPanel\QuestionRecord;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use IContextSource;
use OOUI\ButtonWidget;
use OOUI\Tag;

class Help extends BaseModule {

	const HELP_MODULE_QUESTION_TAG = 'help module question';
	const QUESTION_PREF = 'growthexperiments-help-questions';
	/** @var QuestionRecord[]|null */
	private $recentQuestions = null;

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'help', $context );
	}

	/**
	 * @return string
	 */
	protected function getHeader() {
		return $this->getContext()->msg( 'growthexperiments-homepage-help-header' )->escaped();
	}

	/**
	 * @return string
	 */
	protected function getSubheader() {
		return $this->getContext()->msg( 'growthexperiments-homepage-help-subheader' )->escaped();
	}

	/**
	 * @inheritDoc
	 */
	protected function getActionData() {
		$archivedQuestions = 0;
		$unarchivedQuestions = 0;
		foreach ( $this->getRecentQuestions() as $questionRecord ) {
			if ( $questionRecord->isArchived() ) {
				$archivedQuestions++;
			} else {
				$unarchivedQuestions++;
			}
		}
		return array_merge(
			parent::getActionData(),
			[
				'archivedQuestions' => $archivedQuestions,
				'unarchivedQuestions' => $unarchivedQuestions
			]
		);
	}

	/**
	 * @return string|string[]
	 */
	protected function getModules() {
		return 'ext.growthExperiments.Homepage.Help';
	}

	/**
	 * @inheritDoc
	 */
	protected function getJsConfigVars() {
		return HelpPanel::getUserEmailConfigVars( $this->getContext()->getUser() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		$helpPanelLinkData = HelpPanel::getHelpPanelLinks(
			$this->getContext(),
			$this->getContext()->getConfig()
		);
		return $helpPanelLinkData['helpPanelLinks'] . $helpPanelLinkData['viewMoreLink'] .
			$this->getCtaButton() . $this->getRecentQuestionsSection();
	}

	/**
	 * @return Tag
	 */
	protected function getCtaButton() {
		return ( new Tag( 'div' ) )
			->addClasses( [ 'mw-ge-homepage-help-cta' ] )
			->appendContent( new ButtonWidget( [
				'id' => 'mw-ge-homepage-help-cta',
				'href' => HelpPanel::getHelpDeskTitle(
					$this->getContext()->getConfig()
				)->getLinkURL(),
				'label' => $this->getContext()->msg(
					'growthexperiments-homepage-help-ask-help-desk'
				)->text(),
				'infusable' => true,
			] ) );
	}

	private function getRecentQuestionsSection() {
		$recentQuestionFormatter = new RecentQuestionsFormatter(
			$this->getContext(),
			$this->getRecentQuestions(),
			self::QUESTION_PREF
		);
		return $recentQuestionFormatter->format();
	}

	/**
	 * @return QuestionRecord[]
	 */
	private function getRecentQuestions() {
		if ( $this->recentQuestions !== null ) {
			return $this->recentQuestions;
		}
		$this->recentQuestions = QuestionStoreFactory::newFromContextAndStorage(
			$this->getContext(),
			self::QUESTION_PREF
		)->loadQuestions();
		return $this->recentQuestions;
	}

}
