<?php

namespace GrowthExperiments\HomepageModules;

use ConfigException;
use DateInterval;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HelpPanel;
use GrowthExperiments\HelpPanel\QuestionRecord;
use GrowthExperiments\HelpPanel\QuestionStoreFactory;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\Mentorship\Mentor;
use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
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

	const MENTORSHIP_MODULE_QUESTION_TAG = 'mentorship module question';
	const MENTORSHIP_HELPPANEL_QUESTION_TAG = 'mentorship panel question';
	const QUESTION_PREF = 'growthexperiments-mentor-questions';

	/** @var User */
	private $mentor;

	/** @var QuestionRecord[] */
	private $recentQuestions = [];

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context, ExperimentUserManager $experimentUserManager ) {
		parent::__construct( 'mentorship', $context, $experimentUserManager );
	}

	/**
	 * Get the time a mentor was last active, as a human-readable relative time.
	 * @param User $mentor The mentoring user.
	 * @param User $mentee The mentored user (for time formatting).
	 * @param MessageLocalizer $messageLocalizer
	 * @return string
	 */
	public static function getMentorLastActive(
		User $mentor, User $mentee, MessageLocalizer $messageLocalizer
	) {
		$editTimestamp = new MWTimestamp( $mentor->getLatestEditTimestamp() );
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
	protected function getHeaderIconName() {
		return 'userTalk';
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return implode( "\n", [
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
				'href' => $this->getMentor()->getTalkPage()->getLinkURL(),
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
		return $this->getMode() !== HomepageModule::RENDER_MOBILE_SUMMARY ?
			[
				'ext.growthExperiments.Homepage.Mentorship',
				'ext.growthExperiments.Homepage.RecentQuestions'
			] : [];
	}

	/**
	 * @inheritDoc
	 */
	protected function getJsConfigVars() {
		$mentor = $this->getMentor();
		$genderCache = MediaWikiServices::getInstance()->getGenderCache();
		return [
			'GEHomepageMentorshipMentorName' => $mentor->getName(),
			'GEHomepageMentorshipMentorGender' => $genderCache->getGenderOf( $mentor, __METHOD__ )
		] + HelpPanel::getUserEmailConfigVars( $this->getContext()->getUser() );
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
				'mentorEditCount' => $this->getMentor()->getEditCount(),
				'mentorLastActive' => $this->getMentor()->getLatestEditTimestamp(),
				'archivedQuestions' => $archivedQuestions,
				'unarchivedQuestions' => $unarchivedQuestions
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function canRender() {
		return (bool)$this->getMentor();
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
					'href' => $this->getMentor()->getUserPage()->getLinkURL(),
					'data-link-id' => 'mentor-userpage',
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
			->numParams( $this->getMentor()->getEditCount() )
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
		$mentor = Mentor::newFromMentee( $this->getContext()->getUser() );
		return Html::element( 'div',
			[ 'class' => 'growthexperiments-homepage-mentorship-intro' ],
			$mentor->getIntroText( $this->getContext() ) );
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
			'href' => $this->getMentor()->getTalkPage()->getLinkURL( [
				'action' => 'edit',
				'section' => 'new',
			] ),
			'infusable' => true,
		] );
	}

	/**
	 * @return bool|User The current user's mentor or false if not set.
	 * @throws ConfigException
	 */
	private function getMentor() {
		if ( !$this->mentor ) {
			$mentor = Mentor::newFromMentee( $this->getContext()->getUser() );
			if ( $mentor ) {
				$this->mentor = $mentor->getMentorUser();
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

}
