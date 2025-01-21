<?php

namespace GrowthExperiments\HelpPanel\QuestionPoster;

use GrowthExperiments\Mentorship\MentorManager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\TitleFactory;
use UserNotLoggedIn;
use Wikimedia\Assert\Assert;
use Wikimedia\Stats\StatsFactory;

/**
 * Factory class for selecting the right question poster based on where the questions should go
 * and where they are sent from.
 */
class QuestionPosterFactory {

	/** The question is sent from the Mentorship module of the home page. */
	public const SOURCE_MENTORSHIP_MODULE = 'mentorship module';
	/** The question is sent from the help panel. */
	public const SOURCE_HELP_PANEL = 'help panel';

	/** The question is sent to the wiki's helpdesk. */
	public const TARGET_HELPDESK = 'helpdesk';
	/** The question is sent to the talk page of the asking user's mentor. */
	public const TARGET_MENTOR_TALK = 'mentor talk page';

	private WikiPageFactory $wikiPageFactory;
	private TitleFactory $titleFactory;
	private MentorManager $mentorManager;
	private PermissionManager $permissionManager;
	private bool $helpDeskPostOnTop;
	private StatsFactory $statsFactory;

	private bool $confirmEditInstalled;
	private bool $flowInstalled;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFactory $titleFactory
	 * @param MentorManager $mentorManager
	 * @param PermissionManager $permissionManager
	 * @param bool $helpDeskPostOnTop Whether to post on top of the help desk
	 *   (as opposed to the bottom). Only affects wikitext pages.
	 * @param statsFactory $statsFactory
	 * @param bool $confirmEditInstalled
	 * @param bool $flowInstalled
	 */
	public function __construct(
		WikiPageFactory $wikiPageFactory,
		TitleFactory $titleFactory,
		MentorManager $mentorManager,
		PermissionManager $permissionManager,
		bool $helpDeskPostOnTop,
		StatsFactory $statsFactory,
		bool $confirmEditInstalled,
		bool $flowInstalled
	) {
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFactory = $titleFactory;
		$this->mentorManager = $mentorManager;
		$this->permissionManager = $permissionManager;
		$this->helpDeskPostOnTop = $helpDeskPostOnTop;
		$this->statsFactory = $statsFactory;
		$this->confirmEditInstalled = $confirmEditInstalled;
		$this->flowInstalled = $flowInstalled;
	}

	/**
	 * @param string $source One of the SOURCE_* constants.
	 * @param string $target One of the TARGET_* constants.
	 * @param IContextSource $context
	 * @param string $body Wikitext of the question.
	 * @param string $relevantTitle Title of the page the question is about, if any.
	 * @return QuestionPoster
	 * @throws UserNotLoggedIn
	 */
	public function getQuestionPoster(
		string $source,
		string $target,
		IContextSource $context,
		string $body,
		string $relevantTitle = ''
	): QuestionPoster {
		Assert::parameter(
			in_array( $source, [ self::SOURCE_MENTORSHIP_MODULE, self::SOURCE_HELP_PANEL ], true ),
			'$source', 'must be one of the QuestionPosterFactory::SOURCE_* constants' );
		Assert::parameter(
			in_array( $target, [ self::TARGET_HELPDESK, self::TARGET_MENTOR_TALK ], true ),
			'$target', 'must be one of the QuestionPosterFactory::TARGET_* constants' );

		if ( $target === self::TARGET_HELPDESK ) {
			$questionPoster = new HelpdeskQuestionPoster(
				$this->wikiPageFactory,
				$this->titleFactory,
				$this->permissionManager,
				$this->statsFactory,
				$this->confirmEditInstalled,
				$this->flowInstalled,
				$context,
				$body,
				$relevantTitle
			);
			$questionPoster->setPostOnTop( $this->helpDeskPostOnTop );
			return $questionPoster;
		} elseif ( $source === self::SOURCE_HELP_PANEL ) {
			return new HelppanelMentorQuestionPoster(
				$this->wikiPageFactory,
				$this->titleFactory,
				$this->mentorManager,
				$this->permissionManager,
				$this->statsFactory,
				$this->confirmEditInstalled,
				$this->flowInstalled,
				$context,
				$body,
				$relevantTitle
			);
		} else {
			return new HomepageMentorQuestionPoster(
				$this->wikiPageFactory,
				$this->titleFactory,
				$this->mentorManager,
				$this->permissionManager,
				$this->statsFactory,
				$this->confirmEditInstalled,
				$this->flowInstalled,
				$context,
				$body,
				$relevantTitle
			);
		}
	}

}
