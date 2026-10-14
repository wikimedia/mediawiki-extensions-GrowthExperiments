<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\HelpPanel;
use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Language\MessageLocalizer;
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
		MessageLocalizer $messageLocalizer,
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
			$messageLocalizer,
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
		if ( !$this->getWikitextLinkTarget() ) {
			return $this->messageLocalizer
				->msg( 'growthexperiments-help-panel-question-subject-template' )
				->inContentLanguage()->plain();
		}
		$contentModel = $this->getLinkTargetContentModel();
		// T437928 - we want to display the description for Wikibase content models (e.g. item, property)
		// and the most straightforward way to do that is to have the wikitext for this message include a
		// Lua template to expand the description in the user language (e.g. {{Q|Q12}}). But we do not know
		// if such a template exists on the target wiki.
		// Let the community managing the target wiki take responsibility for the existence of a message
		// for the target content type (i.e. '...-subject-template-with-title-wikibase-item'), and ensuring
		// that the template mentioned in the translation also exists on the wiki.
		$titleMsg = 'growthexperiments-help-panel-question-subject-template-with-title-' . $contentModel;
		if ( !$this->messageLocalizer->msg( $titleMsg )->exists() ) {
			$titleMsg = 'growthexperiments-help-panel-question-subject-template-with-title';
		}
		/**
		 * Expected messages here are:
		 *  - growthexperiments-help-panel-question-subject-template-with-title
		 *  - growthexperiments-help-panel-question-subject-template-with-title-wikibase-item
		 *  - growthexperiments-help-panel-question-subject-template-with-title-wikibase-property
		 */
		return $this->messageLocalizer
				->msg( $titleMsg )
				->params( $this->getWikitextLinkTarget() )
				->inContentLanguage()->plain();
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
