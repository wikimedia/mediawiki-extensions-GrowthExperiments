<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\HelpPanel;
use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Stats\StatsFactory;

/**
 * QuestionPoster variant for asking questions on the wiki's help desk.
 */
class HelpdeskQuestionPoster extends QuestionPoster {

	public const QUESTION_PREF = 'growthexperiments-helppanel-questions';

	private HelpPanel $helpPanel;

	public function __construct(
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		RedirectLookup $redirectLookup,
		PermissionManager $permissionManager,
		StatsFactory $statsFactory,
		HelpPanel $helpPanel,
		bool $confirmEditInstalled,
		bool $flowInstalled,
		IContextSource $context,
		string $body,
		string $relevantTitleRaw = ''
	) {
		parent::__construct(
			$wikiPageFactory,
			$titleFactory,
			$redirectLookup,
			$permissionManager,
			$statsFactory,
			$confirmEditInstalled,
			$flowInstalled,
			$context,
			$body,
			$relevantTitleRaw
		);
		$this->helpPanel = $helpPanel;
	}

	/**
	 * @inheritDoc
	 */
	protected function getTag() {
		return HelpPanel::HELPDESK_QUESTION_TAG;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSectionHeaderTemplate() {
		return $this->getWikitextLinkTarget() ?
			$this->getContext()
				->msg( 'growthexperiments-help-panel-question-subject-template-with-title' )
				->params( $this->getWikitextLinkTarget() )
				->inContentLanguage()->text() :
			$this->getContext()
				->msg( 'growthexperiments-help-panel-question-subject-template' )
				->inContentLanguage()->text();
	}

	/**
	 * @inheritDoc
	 * @throws LogicException if called when Help desk title is not defined
	 */
	protected function getDirectTargetTitle() {
		$title = $this->helpPanel->getHelpDeskTitle();
		if ( !$title ) {
			throw new LogicException(
				__METHOD__ . ' not expected to be called without a Help desk title'
			);
		}
		return $title;
	}

	/**
	 * @inheritDoc
	 */
	protected function getQuestionStoragePref() {
		return self::QUESTION_PREF;
	}
}
