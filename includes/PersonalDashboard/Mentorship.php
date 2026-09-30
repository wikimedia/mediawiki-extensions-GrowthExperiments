<?php

declare( strict_types = 1 );

namespace GrowthExperiments\PersonalDashboard;

use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use GrowthExperiments\HomepageModules\Mentorship as HomepageMentorship;
use GrowthExperiments\HomepageModules\RecentQuestionsFormatter;
use GrowthExperiments\Mentorship\Hooks\MentorUserOptionsHooks;
use GrowthExperiments\Mentorship\IMentorManager;
use GrowthExperiments\Mentorship\Mentor;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use MediaWiki\Cache\GenderCache;
use MediaWiki\Context\IContextSource;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\PersonalDashboard\Modules\BaseModule;
use MediaWiki\Html\Html;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\Codex\Component\HtmlSnippet;
use Wikimedia\Codex\Localization\MediaWikiLocalization;
use Wikimedia\Codex\Utility\Codex;

/**
 * A lightweight Codex reimagining of the Newcomer Homepage mentorship module for
 * the Personal Dashboard. It reuses GrowthExperiments' mentor data services but
 * renders its own card rather than wrapping the live OOUI module.
 *
 * Rendered server-side: the card body (mentor identity, tenure, intro) is plain
 * HTML a no-JS visitor gets whole. The "ask a question" call to action is a real
 * link to the mentor's talk page new-section editor; the behavior module upgrades
 * that click into a Codex dialog. The header menu (opt out or in, about mentors)
 * holds client state, so only the behavior module renders it.
 *
 * The mentor shown is the effective mentor, which already routes around an away
 * primary to the backup, so this is the mentor who will actually answer, both in
 * the card and as the question's destination. A notice tells the mentee when
 * this is not their usual mentor.
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
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly UserFactory $userFactory,
		private readonly GenderCache $genderCache,
		private readonly UserOptionsLookup $userOptionsLookup
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

	/**
	 * Both card states get the menu. The behavior module teleports it into the
	 * slot, because the island loader skips server-rendered modules.
	 * @return bool
	 */
	protected function hasHeaderMenu(): bool {
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
		// The special page calls this for every supported module, also one that
		// does not render. Skip the message parsing and gender lookup then.
		if ( !$this->canRender() ) {
			return [];
		}
		$vars = [
			'wgGrowthExperimentsMentorship' => [
				'headerSlot' => 'pd-header-slot-' . $this->getName(),
				'optedOut' => $this->getMentorshipState() === IMentorManager::MENTORSHIP_OPTED_OUT,
				'preintroDismissedPref' => MentorUserOptionsHooks::PREINTRO_DISMISSED_PREF,
			],
		];
		$mentor = $this->getMentor();
		if ( !$mentor ) {
			return $vars;
		}
		$mentorName = $mentor->getUserIdentity()->getName();
		$userName = $this->getUser()->getName();
		// The public-posting notice embeds a link to the mentor's talk page. It is
		// the same message the Homepage module's dialog shows; the link is a raw
		// HTML param because rawParams() is substituted after parse() runs.
		$talkLinkHtml = Html::element(
			'a',
			[ 'href' => $this->getMentorTalkPage()->getLinkURL() ],
			$this->msg( self::MSG_PREFIX . 'questionreview-header-mentor-talk-link-text' )
				->params( $mentorName, $userName )->text()
		);
		return $vars + [
			'wgGrowthExperimentsMentorshipPoster' => [
				'dialogTitle' => $this->msg( self::MSG_PREFIX . 'dialog-title' )
					->params( $mentorName, $userName )->text(),
				'notice' => $this->msg( self::MSG_PREFIX . 'questionreview-header' )
					->params( $mentorName, $userName )
					->rawParams( $talkLinkHtml )
					->parse(),
				// The question is saved on the mentor's talk page, so use its warning.
				'copyrightWarning' => EditPage::getCopyrightWarning(
					$this->getMentorTalkPage(), 'parse', $this
				),
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
	protected function getBody(): string {
		if ( $this->getMentorshipState() === IMentorManager::MENTORSHIP_OPTED_OUT ) {
			return $this->getOptInCardHtml();
		}
		return implode( "\n", [
			$this->getAwayNoticeHtml() ?? $this->getPreintroHtml(),
			$this->getMentorCardHtml(),
			$this->getQuestionButtonHtml(),
			$this->getRecentQuestionsHtml(),
		] );
	}

	/**
	 * Why the mentee has a mentor, as a notice above the mentor card. It replaces
	 * the subheader, because design shows it as a message and not as header text.
	 * Once dismissed, it does not show again.
	 * @return string
	 */
	private function getPreintroHtml(): string {
		if ( $this->userOptionsLookup->getBoolOption(
			$this->getUser(), MentorUserOptionsHooks::PREINTRO_DISMISSED_PREF
		) ) {
			return '';
		}
		return $this->getMessageHtml(
			'personal-dashboard-mentorship__preintro',
			$this->msg( self::MSG_PREFIX . 'preintro' )
				->params( $this->getMentorName() )
				->escaped()
		);
	}

	/**
	 * When the usual mentor is away, the shown mentor is the backup. Tell the
	 * mentee, with links to both. Not affected by the intro dismissal, because the
	 * mentee must know this each time.
	 * @return string|null Null when the usual mentor is the shown mentor.
	 */
	private function getAwayNoticeHtml(): ?string {
		$primary = $this->mentorManager->getMentorForUserIfExists( $this->getUser() );
		if ( !$primary || $primary->getUserIdentity()->equals( $this->getMentor()->getUserIdentity() ) ) {
			return null;
		}
		$primaryUser = $primary->getUserIdentity();
		return $this->getMessageHtml(
			'personal-dashboard-mentorship__away',
			$this->msg( self::MSG_PREFIX . 'away-notice' )
				->rawParams(
					$this->getUserPageLinkHtml( $this->getMentor()->getUserIdentity() ),
					$this->getUserPageLinkHtml( $primaryUser )
				)
				->params( $primaryUser->getName() )
				->escaped()
		);
	}

	/**
	 * A plain link: a mentor with no user page must not get a red link to the
	 * edit form.
	 * @param UserIdentity $user
	 * @return string
	 */
	private function getUserPageLinkHtml( UserIdentity $user ): string {
		return Html::rawElement(
			'a',
			[ 'href' => $this->userFactory->newFromUserIdentity( $user )->getUserPage()->getLinkURL() ],
			Html::element( 'bdi', [], $user->getName() )
		);
	}

	/**
	 * A dismissable Codex notice message. The behavior module does the dismissal,
	 * so the button is hidden without JS.
	 * @param string $class
	 * @param string $html The message content, already escaped.
	 * @return string
	 */
	private function getMessageHtml( string $class, string $html ): string {
		$codex = $this->getCodex();
		// Codex PHP has no dismiss button, so it goes in the content. The Codex
		// styles position it against the message, so it looks the same.
		$dismissHtml = $codex->button()
			->setIconOnly( true )
			->setWeight( 'quiet' )
			->setIconClass( 'personal-dashboard-mentorship__message-dismiss-icon' )
			->setAttributes( [
				'class' => 'cdx-message__dismiss-button personal-dashboard-mentorship__message-dismiss',
				'aria-label' => $this->msg( 'cdx-message-dismiss-button-label' )->text(),
			] )
			->getHtml();
		return $codex->message()
			->setContent( new HtmlSnippet( $html . $dismissHtml ) )
			->setAttributes( [
				'class' => "cdx-message--user-dismissable personal-dashboard-mentorship__message $class",
			] )
			->getHtml();
	}

	/**
	 * The mentee's recently asked questions, reusing the Homepage module's store
	 * and formatter wholesale. Renders nothing when there are none. The poster
	 * writes to this same store, so a question asked here shows up on re-render.
	 * @return string
	 */
	private function getRecentQuestionsHtml(): string {
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

	/**
	 * The opt-in card an opted-out user sees in place of the mentor card: what a
	 * mentor is for, and a button to get one. The card is hidden without JS,
	 * because opting in goes through the API.
	 * @return string
	 */
	private function getOptInCardHtml(): string {
		$textHtml = Html::element(
			'p',
			[ 'class' => 'personal-dashboard-mentorship__optin-text' ],
			$this->msg( self::MSG_PREFIX . 'optin-text' )->text()
		);
		$buttonHtml = $this->getCodex()->button()
			->setLabel( $this->msg( self::MSG_PREFIX . 'optin-button' )->text() )
			->setAttributes( [ 'class' => 'personal-dashboard-mentorship__optin' ] )
			->getHtml();
		return $textHtml . $buttonHtml;
	}

	/**
	 * The mentor as a Codex card: an initial avatar, the username linked to their
	 * user page, their intro, and how long they have edited.
	 * @return string
	 */
	private function getMentorCardHtml(): string {
		$codex = $this->getCodex();
		$mentorUser = $this->getMentor()->getUserIdentity();
		$mentorName = $mentorUser->getName();

		// Only the name is a link, so screen readers read the intro as text. CSS
		// stretches it over the card, so the whole card is a link, as design asks.
		$titleHtml = Html::rawElement(
			'a',
			[
				'class' => 'personal-dashboard-mentorship__card-link',
				'href' => $this->userFactory->newFromUserIdentity( $mentorUser )->getUserPage()->getLinkURL(),
			],
			Html::element( 'bdi', [], $mentorName )
		);

		// Codex has no avatar component yet, so the thumbnail shows the design's
		// initial in a circle (with CSS). Decorative: the name follows it.
		$avatar = $codex->thumbnail()
			->setAttributes( [
				'class' => 'personal-dashboard-mentorship__avatar',
				// Not ?:, which would drop an initial of "0".
				'data-initial' => (string)grapheme_substr( $mentorName, 0, 1 ),
				'aria-hidden' => 'true',
			] );

		// Accounts from before registration was logged have no timestamp; leave
		// the line out, not guess.
		$registration = $this->userRegistrationLookup->getRegistration( $mentorUser );
		$supportingText = $registration ? $this->msg( self::MSG_PREFIX . 'mentor-editing-since' )
			->params( $this->getContext()->getLanguage()->formatNumNoSeparators(
				MWTimestamp::getInstance( $registration )->format( 'Y' )
			) )
			->params( $mentorName )
			->text() : '';

		// Not setTitle(): it calls trim() on an HtmlSnippet, which is a TypeError.
		return $codex->card(
			title: new HtmlSnippet( $titleHtml ),
			description: new HtmlSnippet( $this->getIntroHtml() ),
			supportingText: $supportingText,
			thumbnail: $avatar,
			// For the Codex link card hover style.
			attributes: [ 'class' => 'cdx-card--is-link personal-dashboard-mentorship__card' ]
		)->getHtml();
	}

	/**
	 * The mentor's intro, quoted when they wrote their own; otherwise the default
	 * blurb about what a mentor is for.
	 * @return string
	 */
	private function getIntroHtml(): string {
		$mentor = $this->getMentor();
		if ( $mentor->hasCustomIntroText() ) {
			// The mentor's own words, so render them as a quote; the mentor name
			// above is the attribution. Capped like the Homepage module so a long
			// intro can't blow out the card.
			$introText = $this->getContext()->getLanguage()->truncateForVisual(
				$mentor->getIntroText(),
				MentorProvider::INTRO_TEXT_LENGTH
			);
			// A <blockquote> in the description <span> is not valid HTML, but
			// browsers keep it there, and design wants the skin's quote style.
			// dir=auto so a content-language intro (which may be RTL) lays out
			// by its own direction inside an interface of any direction.
			return Html::element(
				'blockquote',
				[ 'class' => 'personal-dashboard-mentorship__quote', 'dir' => 'auto' ],
				$introText
			);
		}
		// A generic description of what a mentor is for, not the mentor's words.
		return Html::element(
			'span',
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
	private function getQuestionButtonHtml(): string {
		$href = $this->getMentorTalkPage()->getLocalURL( [
			'action' => 'edit',
			'section' => 'new',
			'preloadtitle' => $this->getQuestionSubject(),
		] );
		return $this->getCodex()->button()
			->setLabel(
				$this->msg( self::MSG_PREFIX . 'question-button' )
					->params( $this->getMentorName(), $this->getUser()->getName() )
					->text()
			)
			->setHref( $href )
			->setAttributes( [ 'class' => 'personal-dashboard-mentorship__ask' ] )
			->getHtml();
	}

	private function getCodex(): Codex {
		return new Codex( new MediaWikiLocalization( $this->getContext() ) );
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
