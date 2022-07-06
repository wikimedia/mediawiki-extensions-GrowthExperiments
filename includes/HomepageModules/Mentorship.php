<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use ConfigException;
use DateInterval;
use GenderCache;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HelpPanel;
use GrowthExperiments\HelpPanel\QuestionRecord;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use GrowthExperiments\MentorDashboard\MentorTools\MentorStatusManager;
use GrowthExperiments\Mentorship\MentorManager;
use GrowthExperiments\Mentorship\Provider\MentorProvider;
use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;
use MWTimestamp;
use OOUI\ButtonWidget;
use OOUI\IconWidget;
use User;

/**
 * This is the "Mentorship" module. It shows your mentor and
 * provides ways to interact with them.
 *
 * @package GrowthExperiments\HomepageModules
 */
class Mentorship extends BaseModule {

	public const MENTORSHIP_MODULE_QUESTION_TAG = 'mentorship module question';
	public const MENTORSHIP_HELPPANEL_QUESTION_TAG = 'mentorship panel question';
	public const QUESTION_PREF = 'growthexperiments-mentor-questions';

	/** @var UserIdentity */
	private $mentor;

	/** @var QuestionRecord[] */
	private $recentQuestions = [];

	/** @var MentorManager */
	private $mentorManager;

	/** @var MentorStatusManager */
	private $mentorStatusManager;

	/** @var GenderCache */
	private $genderCache;

	/**
	 * @param IContextSource $context
	 * @param Config $wikiConfig
	 * @param ExperimentUserManager $experimentUserManager
	 * @param MentorManager $mentorManager
	 * @param MentorStatusManager $mentorStatusManager
	 * @param GenderCache $genderCache
	 */
	public function __construct(
		IContextSource $context,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager,
		MentorManager $mentorManager,
		MentorStatusManager $mentorStatusManager,
		GenderCache $genderCache
	) {
		parent::__construct( 'mentorship', $context, $wikiConfig, $experimentUserManager );
		$this->mentorManager = $mentorManager;
		$this->mentorStatusManager = $mentorStatusManager;
		$this->genderCache = $genderCache;
	}

	/**
	 * Get the time a mentor was last active, as a human-readable relative time.
	 * @param UserIdentity $mentor The mentoring user.
	 * @param User $mentee The mentored user (for time formatting).
	 * @param MessageLocalizer $messageLocalizer
	 * @return string
	 */
	public static function getMentorLastActive(
		UserIdentity $mentor, User $mentee, MessageLocalizer $messageLocalizer
	) {
		$editTimestamp = new MWTimestamp( MediaWikiServices::getInstance()->getUserEditTracker()
			->getLatestEditTimestamp( $mentor ) );
		$editTimestamp->offsetForUser( $mentee );
		$editDate = $editTimestamp->format( 'Ymd' );

		$now = new MWTimestamp();
		$now->offsetForUser( $mentee );
		$timeDiff = $now->diff( $editTimestamp );

		$today = $now->format( 'Ymd' );
		$yesterday = $now->timestamp->sub( new DateInterval( 'P1D' ) )->format( 'Ymd' );

		if ( $editDate === $today ) {
			$text = $messageLocalizer
				->msg( 'growthexperiments-homepage-mentorship-mentor-active-today' )
				->params( $mentor->getName() )
				->text();
		} elseif ( $editDate === $yesterday ) {
			$text = $messageLocalizer
				->msg( 'growthexperiments-homepage-mentorship-mentor-active-yesterday' )
				->params( $mentor->getName() )
				->text();
		} else {
			$text = $messageLocalizer
				->msg( 'growthexperiments-homepage-mentorship-mentor-active-days-ago' )
				->params( $mentor->getName() )
				->numParams( $timeDiff->days )
				->text();
		}
		return $text;
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-mentorship-header' )
			->params( $this->getContext()->getUser()->getName() )
			->params( $this->getMentor()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function buildSection( $name, $content, $tag = 'div' ) {
		if ( $name === 'header' && $this->getMode() === self::RENDER_DESKTOP ) {
			return Html::rawElement(
				'div',
				[ 'class' => 'growthexperiments-homepage-mentorship-header-wrapper' ],
				implode( "\n", [
					parent::buildSection( $name, $content, $tag ),
					$this->getEllipsisWidget()
				] )
			);
		}
		return parent::buildSection( $name, $content, $tag );
	}

	/**
	 * @return string
	 */
	private function getEllipsisWidget() {
		// NOTE: This will be replaced with ButtonMenuSelectWidget in EllipsisMenu.js on the
		// client side.
		return Html::rawElement(
			'div',
			[ 'class' => 'growthexperiments-homepage-mentorship-ellipsis' ],
			new ButtonWidget( [
				'id' => 'mw-ge-homepage-mentorship-ellipsis',
				'icon' => 'ellipsis',
				'framed' => false,
				'invisibleLabel' => true,
				'infusable' => true,
			] )
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'userTalk';
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return implode( "\n", [
			$this->getAboutMentorshipElement(),
			$this->getMentorUsernameElement( true ),
			$this->getMentorInfo(),
			$this->getIntroText(),
			$this->getQuestionButton(),
			$this->getRecentQuestionsSection(),
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return implode( "\n", [
			Html::element( 'p', [], $this->msg(
				'growthexperiments-homepage-mentorship-preintro',
				$this->getMentor()->getName()
			)->text() ),
			$this->getMentorUsernameElement( false ),
			$this->getLastActive(),
		] );
	}

	/**
	 * @inheritDoc
	 */
	protected function getFooter() {
		return Html::element(
			'a',
			[
				'href' => User::newFromIdentity( $this->getMentor() )->getTalkPage()->getLinkURL(),
				'data-link-id' => 'mentor-usertalk',
			],
			$this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-mentor-conversations' )
				->params( $this->getMentor()->getName() )
				->params( $this->getContext()->getUser()->getName() )
				->text()
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(),
			[ 'oojs-ui.styles.icons-user' ]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getModules() {
		return $this->getMode() !== self::RENDER_MOBILE_SUMMARY ?
			[ 'ext.growthExperiments.Homepage.Mentorship' ] : [];
	}

	/**
	 * @inheritDoc
	 */
	protected function getJsConfigVars() {
		$mentor = $this->getMentor();
		$effectiveMentor = $this->mentorManager->getEffectiveMentorForUserSafe(
			$this->getUser()
		)->getUserIdentity();
		return [
			'GEHomepageMentorshipMentorName' => $mentor->getName(),
			'GEHomepageMentorshipMentorGender' => $this->getMentorGender(),
			'GEHomepageMentorshipEffectiveMentorName' => $effectiveMentor->getName(),
			'GEHomepageMentorshipEffectiveMentorGender' => $this->getUserGender( $effectiveMentor ),
			'GEHomepageMentorshipBackAt' => $this->getContext()->getLanguage()->date(
				$this->mentorStatusManager->getMentorBackTimestamp( $mentor ) ?? ''
			)
		] + HelpPanel::getUserEmailConfigVars( $this->getContext()->getUser() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getActionData() {
		$editTracker = MediaWikiServices::getInstance()->getUserEditTracker();
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
				'mentorEditCount' => $editTracker->getUserEditCount( $this->getMentor() ),
				'mentorLastActive' => $editTracker->getLatestEditTimestamp( $this->getMentor() ),
				'archivedQuestions' => $archivedQuestions,
				'unarchivedQuestions' => $unarchivedQuestions
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function canRender() {
		return $this->mentorManager->getMentorshipStateForUser(
			$this->getUser()
		) === MentorManager::MENTORSHIP_ENABLED &&
			$this->mentorManager->getEffectiveMentorForUserSafe( $this->getUser() ) !== null;
	}

	private function getMentorUsernameElement( $link ) {
		$iconElement = new IconWidget( [ 'icon' => 'mentor' ] );
		$usernameElement = Html::element(
			'span',
			[ 'class' => 'growthexperiments-homepage-mentorship-username' ],
			$this->getContext()->getLanguage()->embedBidi(
				$this->getMentor()->getName()
			)
		);
		if ( $link ) {
			$content = Html::rawElement(
				'a',
				[
					'href' => User::newFromIdentity( $this->getMentor() )->getUserPage()->getLinkURL(),
					'data-link-id' => 'mentor-userpage',
					'class' => 'growthexperiments-homepage-mentorship-userlink-link'
				],
				$iconElement . $usernameElement
			);
		} else {
			$content = Html::rawElement(
				'span',
				[],
				$iconElement . $usernameElement
			);
		}
		return Html::rawElement( 'div', [
			'class' => 'growthexperiments-homepage-mentorship-userlink'
		], $content );
	}

	private function getMentorInfo() {
		return Html::rawElement(
			'div',
			[
				'class' => 'growthexperiments-homepage-mentorship-mentorinfo'
			],
			$this->getEditCount() . ' &bull; ' . $this->getLastActive()
		);
	}

	private function getEditCount() {
		$text = $this->getContext()
			->msg( 'growthexperiments-homepage-mentorship-mentor-edits' )
			->numParams( MediaWikiServices::getInstance()->getUserEditTracker()
				->getUserEditCount( $this->getMentor() ) )
			->text();
		return Html::element( 'span', [
			'class' => 'growthexperiments-homepage-mentorship-editcount'
		], $text );
	}

	private function getLastActive() {
		$text = self::getMentorLastActive( $this->getMentor(), $this->getContext()->getUser(),
			$this->getContext() );
		return Html::element( 'span', [
			'class' => 'growthexperiments-homepage-mentorship-lastactive'
		], $text );
	}

	private function getIntroText() {
		$mentor = $this->mentorManager->getMentorForUser( $this->getContext()->getUser() );

		$introText = $this->getContext()->getLanguage()->truncateForVisual(
			$mentor->getIntroText(),
			MentorProvider::INTRO_TEXT_LENGTH
		);
		if ( $mentor->hasCustomIntroText() ) {
			$introText = $this->msg( 'quotation-marks' )
				->inContentLanguage()
				->params( $introText )
				->text();
		}

		return Html::element(
			'div',
			[ 'class' => 'growthexperiments-homepage-mentorship-intro' ],
			$introText
		);
	}

	private function getQuestionButton() {
		return new ButtonWidget( [
			'id' => 'mw-ge-homepage-mentorship-cta',
			'classes' => [ 'growthexperiments-homepage-mentorship-cta' ],
			'active' => false,
			'label' => $this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-question-button' )
				->params( $this->getMentor()->getName() )
				->params( $this->getContext()->getUser()->getName() )
				->text(),
			// nojs action
			'href' => User::newFromIdentity( $this->getMentor() )->getTalkPage()->getLinkURL( [
				'action' => 'edit',
				'section' => 'new',
			] ),
			'infusable' => true,
		] );
	}

	/**
	 * @return UserIdentity|false The current user's mentor or false if not set.
	 * @throws ConfigException
	 */
	private function getMentor() {
		if ( !$this->mentor ) {
			$mentor = $this->mentorManager->getMentorForUserSafe( $this->getContext()->getUser() );
			if ( $mentor ) {
				$this->mentor = $mentor->getUserIdentity();
			} else {
				return false;
			}
		}
		return $this->mentor;
	}

	private function getRecentQuestionsSection() {
		$recentQuestionFormatter = new RecentQuestionsFormatter(
			$this->getContext(),
			$this->getRecentQuestions(),
			self::QUESTION_PREF
		);
		return $recentQuestionFormatter->format();
	}

	private function getRecentQuestions() {
		if ( count( $this->recentQuestions ) ) {
			return $this->recentQuestions;
		}
		$this->recentQuestions = QuestionStoreFactory::newFromContextAndStorage(
			$this->getContext(),
			self::QUESTION_PREF
		)->loadQuestions();
		return $this->recentQuestions;
	}

	private function getAboutMentorshipElement() {
		return Html::rawElement(
			'p',
			[ 'class' => 'growthexperiments-homepage-mentorship-about' ],
			implode( "\n", [
				Html::element(
					'span',
					[],
					$this->msg(
						'growthexperiments-homepage-mentorship-preintro',
						$this->getMentor()->getName()
					)->text()
				),
				Html::element(
					'a',
					[
						'id' => 'growthexperiments-homepage-mentorship-learn-more',
						'href' => '#',
					],
					$this->msg( 'growthexperiments-homepage-mentorship-learn-more' )->text()
				)
			] )
		);
	}

	/**
	 * Get the gender of the specified user
	 *
	 * @param UserIdentity $user
	 * @return string
	 */
	private function getUserGender( UserIdentity $user ): string {
		return $this->genderCache->getGenderOf( $user, __METHOD__ );
	}

	/**
	 * Get the gender of the mentor
	 *
	 * @return string
	 */
	private function getMentorGender(): string {
		return $this->getUserGender( $this->getMentor() );
	}

}
