<?php

declare( strict_types = 1 );

namespace GrowthExperiments\PersonalDashboard;

use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use GrowthExperiments\HomepageModules\Mentorship as HomepageMentorship;
use GrowthExperiments\HomepageModules\RecentQuestionsFormatter;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Cache\GenderCache;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Html\Html;
use MediaWiki\Title\Title;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserFactory;

/**
 * A lightweight Codex reimagining of the Newcomer Homepage mentorship module for
 * the Personal Dashboard. It reuses GrowthExperiments' mentor data services but
 * renders its own card rather than wrapping the live OOUI module.
 *
 * Rendered server-side: the card body (mentor identity, activity, intro) is plain
 * HTML a no-JS visitor gets whole. The one interactive element, the "ask a
 * question" call to action, is a real link to the mentor's talk page new-section
 * editor; the poster behavior module upgrades that click into a Codex dialog.
 *
 * The mentor shown is the effective mentor, which already routes around an away
 * primary to the backup, so this is the mentor who will actually answer, both in
 * the card and as the question's destination.
 */
class Mentorship extends BaseModule {

	private const string MSG_PREFIX = 'growthexperiments-homepage-mentorship-';

	/** @var Mentor|null|false The effective mentor, resolved once; false until resolved. */
	private $mentor = false;

	/** @var int|null The mentee's mentorship state, resolved once; null until resolved. */
	private ?int $mentorshipState = null;

	public function __construct(
		IContextSource $context,
		private readonly IMentorManager $mentorManager,
		private readonly UserEditTracker $userEditTracker,
		private readonly UserFactory $userFactory,
		private readonly GenderCache $genderCache
	) {
		parent::__construct( $context );
	}

	/**
	 * @return Mentor|null The effective mentor for the current user, or null if
	 * they have none. Resolved once and cached, since canRender() and the body
	 * both need it.
	 */
	private function getMentor(): ?Mentor {
		if ( $this->mentor === false ) {
			$this->mentor = $this->mentorManager->getEffectiveMentorForUserSafe( $this->getUser() );
		}
		return $this->mentor;
	}

	/**
	 * @return int The mentee's mentorship state, resolved once and cached; several
	 * of the render methods branch on it.
	 */
	private function getMentorshipState(): int {
		$this->mentorshipState ??= $this->mentorManager->getMentorshipStateForUser( $this->getUser() );
		return $this->mentorshipState;
	}

	/**
	 * The card renders in two states: a mentored user with an effective mentor
	 * gets the mentor card, an opted-out user gets the opt-in card. Everyone else
	 * (mentorship disabled, or no mentor we can resolve) gets no card.
	 * @return bool
	 */
	protected function canRender(): bool {
		$state = $this->getMentorshipState();
		return ( $state === IMentorManager::MENTORSHIP_ENABLED && $this->getMentor() !== null )
			|| $state === IMentorManager::MENTORSHIP_OPTED_OUT;
	}

	/**
	 * Tag the opt-in card so it can be hidden without JS: opting in goes through
	 * the API, so the card has no action to offer a no-JS visitor.
	 * @return string[]
	 */
	protected function getCssClasses(): array {
		if ( $this->getMentorshipState() === IMentorManager::MENTORSHIP_OPTED_OUT ) {
			return [ 'personal-dashboard-mentorship-optin-card' ];
		}
		return [];
	}

	/**
	 * The body is server HTML; the poster upgrades the ask link in place.
	 * @return bool
	 */
	protected function serverRendered(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function getModuleStyles(): array {
		return [ 'ext.growthExperiments.PersonalDashboard.Mentorship.styles' ];
	}

	/**
	 * The poster behavior module. Server-rendered modules are skipped by the
	 * client island loader, so this is loaded server-side; it runs against the
	 * card's DOM and upgrades the ask link into a Codex dialog.
	 * @inheritDoc
	 */
	protected function getModules(): array {
		return [ 'ext.growthExperiments.PersonalDashboard.Mentorship' ];
	}

	/**
	 * The poster dialog's strings, resolved server-side. The GENDER-aware ones are
	 * rendered here because the server knows the mentor's and mentee's genders;
	 * resolving GENDER for an arbitrary username client-side is not reliable. The
	 * poster's write target and the posted question's URL come from the question
	 * poster API, so they are not passed here.
	 * @inheritDoc
	 */
	public function getJsConfigVars(): array {
		$mentor = $this->getMentor();
		if ( !$mentor ) {
			return [];
		}
		$mentorName = $mentor->getUserIdentity()->getName();
		$userName = $this->getUser()->getName();
		// The public-posting notice embeds a link to the mentor's talk page. It is
		// the same message the Homepage module's dialog shows; the link is a raw
		// HTML param because rawParams() is substituted after parse() runs.
		$talkLink = Html::element(
			'a',
			[ 'href' => $this->getMentorTalkPage()->getLinkURL() ],
			$this->msg( self::MSG_PREFIX . 'questionreview-header-mentor-talk-link-text' )
				->params( $mentorName, $userName )->text()
		);
		return [
			'wgGrowthExperimentsMentorshipPoster' => [
				'dialogTitle' => $this->msg( self::MSG_PREFIX . 'dialog-title' )
					->params( $mentorName, $userName )->text(),
				'notice' => $this->msg( self::MSG_PREFIX . 'questionreview-header' )
					->params( $mentorName, $userName )
					->rawParams( $talkLink )
					->parse(),
				'confirmation' => $this->msg( self::MSG_PREFIX . 'confirmation-text' )
					->params( $mentorName, $userName )->text(),
				'viewText' => $this->msg( self::MSG_PREFIX . 'view-question-text' )
					->params( $mentorName, $userName )->text(),
				// The question store's pref key, so the client refreshes the same
				// store the server rendered and targets the same wrapper class,
				// with no key duplicated across PHP and JS.
				'storage' => HomepageMentorship::QUESTION_PREF,
			],
			// The mentor's gender, so the opt-out dialog's GENDER-aware text reads
			// right; resolving GENDER for a username is not reliable client-side.
			'wgGrowthExperimentsMentorshipMentorGender' =>
				$this->genderCache->getGenderOf( $mentor->getUserIdentity(), __METHOD__ ),
		];
	}

	/** @inheritDoc */
	protected function getHeaderText(): string {
		if ( $this->getMentorshipState() === IMentorManager::MENTORSHIP_OPTED_OUT ) {
			return $this->msg( self::MSG_PREFIX . 'optin-header' )->text();
		}
		// getJsData() calls this for every supported module before the enabled
		// gate, so it runs even for a visitor with no mentor. Return nothing then;
		// canRender() keeps the card itself from rendering.
		$mentor = $this->getMentor();
		if ( !$mentor ) {
			return '';
		}
		return $this->msg( self::MSG_PREFIX . 'header' )
			->params( $this->getUser()->getName(), $mentor->getUserIdentity()->getName() )
			->text();
	}

	/** @inheritDoc */
	protected function getSubheaderText(): string {
		if ( $this->getMentorshipState() === IMentorManager::MENTORSHIP_OPTED_OUT ) {
			return '';
		}
		$mentor = $this->getMentor();
		if ( !$mentor ) {
			return '';
		}
		return $this->msg( self::MSG_PREFIX . 'preintro' )
			->params( $mentor->getUserIdentity()->getName() )
			->text();
	}

	/** @inheritDoc */
	protected function getBody(): string {
		if ( $this->getMentorshipState() === IMentorManager::MENTORSHIP_OPTED_OUT ) {
			return $this->getOptInCard();
		}
		return implode( "\n", [
			$this->getMentorInfo(),
			$this->getIntro(),
			$this->getQuestionButton(),
			$this->getRecentQuestions(),
		] );
	}

	/**
	 * The mentee's recently asked questions, reusing the Homepage module's store
	 * and formatter wholesale. Renders nothing when there are none. The poster
	 * writes to this same store, so a question asked here shows up on re-render.
	 * @return string
	 */
	private function getRecentQuestions(): string {
		$questions = QuestionStoreFactory::newFromContextAndStorage(
			$this->getContext(),
			HomepageMentorship::QUESTION_PREF
		)->loadQuestions();
		return ( new RecentQuestionsFormatter(
			$this->getContext(),
			$questions,
			HomepageMentorship::QUESTION_PREF
		) )->format();
	}

	/** @inheritDoc */
	protected function getFooter(): string {
		if ( $this->getMentorshipState() === IMentorManager::MENTORSHIP_OPTED_OUT ) {
			return '';
		}
		return $this->getConversationsLink() . "\n" . $this->getAboutLink() . "\n" . $this->getOptOutLink();
	}

	/**
	 * A link to the mentor's talk page, where their other conversations live.
	 * @return string
	 */
	private function getConversationsLink(): string {
		return Html::element(
			'a',
			[
				'class' => 'personal-dashboard-mentorship__conversations',
				'href' => $this->getMentorTalkPage()->getLinkURL(),
			],
			$this->msg( self::MSG_PREFIX . 'mentor-conversations' )
				->params( $this->getMentorName(), $this->getUser()->getName() )
				->text()
		);
	}

	/**
	 * A quiet control that opens the existing "about mentorship" info dialog,
	 * ported from the Homepage module's ellipsis menu. It is hidden without JS,
	 * since there is no plain-HTML rendering of the dialog content.
	 * @return string
	 */
	private function getAboutLink(): string {
		return Html::element(
			'button',
			[
				'type' => 'button',
				'class' => [
					'cdx-button',
					'cdx-button--weight-quiet',
					'personal-dashboard-mentorship__about',
				],
			],
			$this->msg( self::MSG_PREFIX . 'ellipsis-menu-about' )->text()
		);
	}

	/**
	 * A quiet control to opt out of mentorship. The behavior module upgrades the
	 * click into a Codex confirm dialog; it is hidden without JS, since there is
	 * no plain-HTML opt-out path.
	 * @return string
	 */
	private function getOptOutLink(): string {
		return Html::element(
			'button',
			[
				'type' => 'button',
				'class' => [
					'cdx-button',
					'cdx-button--weight-quiet',
					'personal-dashboard-mentorship__optout',
				],
			],
			$this->msg( self::MSG_PREFIX . 'ellipsis-menu-optout' )->text()
		);
	}

	/**
	 * The opt-in card an opted-out user sees in place of the mentor card: what a
	 * mentor is for, and a button to get one. The button is hidden without JS,
	 * like the opt-out control, since opting in also goes through the API.
	 * @return string
	 */
	private function getOptInCard(): string {
		$text = Html::element(
			'p',
			[ 'class' => 'personal-dashboard-mentorship__intro' ],
			$this->msg( self::MSG_PREFIX . 'optin-text' )->text()
		);
		$button = Html::element(
			'button',
			[
				'type' => 'button',
				'class' => [
					'cdx-button',
					'cdx-button--action-progressive',
					'cdx-button--weight-primary',
					'personal-dashboard-mentorship__optin',
				],
			],
			$this->msg( self::MSG_PREFIX . 'optin-button' )->text()
		);
		return $text . $button;
	}

	/**
	 * The mentor's username, their edit count, and when they were last active, so
	 * the mentee can see they are a real, recently active editor.
	 * @return string
	 */
	private function getMentorInfo(): string {
		$mentorUser = $this->getMentor()->getUserIdentity();

		$name = Html::rawElement(
			'a',
			[
				'class' => 'personal-dashboard-mentorship__name',
				'href' => $this->userFactory->newFromUserIdentity( $mentorUser )->getUserPage()->getLinkURL(),
			],
			Html::element( 'bdi', [], $mentorUser->getName() )
		);

		// getUserEditCount() is null for a user we can't count; skip the count
		// rather than show a misleading "0 edits" for an active mentor. The name
		// and meta are flex siblings, so the separator lives only between the two
		// meta items and never dangles when the name wraps.
		$meta = [];
		$editCount = $this->userEditTracker->getUserEditCount( $mentorUser );
		if ( is_int( $editCount ) ) {
			$meta[] = $this->msg( self::MSG_PREFIX . 'mentor-edits' )->numParams( $editCount )->text();
		}
		$meta[] = HomepageMentorship::getMentorLastActive(
			$mentorUser, $this->getUser(), $this->getContext(), $this->userEditTracker
		);

		return Html::rawElement(
			'div',
			[ 'class' => 'personal-dashboard-mentorship__info' ],
			$name . Html::element(
				'span',
				[ 'class' => 'personal-dashboard-mentorship__meta' ],
				implode( ' • ', $meta )
			)
		);
	}

	/**
	 * The mentor's intro, quoted when they wrote their own; otherwise the default
	 * blurb about what a mentor is for.
	 * @return string
	 */
	private function getIntro(): string {
		$mentor = $this->getMentor();
		if ( $mentor->hasCustomIntroText() ) {
			// The mentor's own words, so render them as a quote; the mentor name
			// above is the attribution. Capped like the Homepage module so a long
			// intro can't blow out the card.
			$introText = $this->getContext()->getLanguage()->truncateForVisual(
				$mentor->getIntroText(),
				MentorProvider::INTRO_TEXT_LENGTH
			);
			return Html::element(
				'blockquote',
				// dir=auto so a content-language intro (which may be RTL) lays out
				// by its own direction inside an interface of any direction.
				[ 'class' => 'personal-dashboard-mentorship__quote', 'dir' => 'auto' ],
				$introText
			);
		}
		// A generic description of what a mentor is for, not the mentor's words.
		return Html::element(
			'p',
			[ 'class' => 'personal-dashboard-mentorship__intro' ],
			$this->msg( self::MSG_PREFIX . 'intro' )
				->params( $this->getMentorName(), $this->getUser()->getName() )
				->text()
		);
	}

	/**
	 * The call to action: a Codex-styled link to the mentor's talk page
	 * new-section editor. With no JS this opens the editor with the section
	 * pre-titled; the poster behavior module intercepts the click and opens a
	 * dialog instead.
	 * @return string
	 */
	private function getQuestionButton(): string {
		$href = $this->getMentorTalkPage()->getLocalURL( [
			'action' => 'edit',
			'section' => 'new',
			'preloadtitle' => $this->getQuestionSubject(),
		] );
		return Html::element(
			'a',
			[
				'class' => [
					'cdx-button',
					'cdx-button--action-progressive',
					'cdx-button--weight-primary',
					'personal-dashboard-mentorship__ask',
				],
				'href' => $href,
			],
			$this->msg( self::MSG_PREFIX . 'question-button' )
				->params( $this->getMentorName(), $this->getUser()->getName() )
				->text()
		);
	}

	/**
	 * @return Title The effective mentor's user talk page, where questions are
	 * posted and their other conversations live.
	 */
	private function getMentorTalkPage(): Title {
		return $this->userFactory
			->newFromUserIdentity( $this->getMentor()->getUserIdentity() )
			->getTalkPage();
	}

	/**
	 * @return string The effective mentor's username.
	 */
	private function getMentorName(): string {
		return $this->getMentor()->getUserIdentity()->getName();
	}

	/**
	 * @return string The new-section heading for a question, in the content
	 * language since it is written to the wiki. Shared by the no-JS link's
	 * preloadtitle and the poster's edit request.
	 */
	private function getQuestionSubject(): string {
		return $this->msg( self::MSG_PREFIX . 'question-subject' )
			->params( $this->getUser()->getName() )
			->inContentLanguage()
			->text();
	}
}
