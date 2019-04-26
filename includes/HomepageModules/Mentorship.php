<?php

namespace GrowthExperiments\HomepageModules;

use ConfigException;
use DateInterval;
use GrowthExperiments\HelpPanel;
use GrowthExperiments\Mentor;
use Html;
use IContextSource;
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

	/** @var User */
	private $mentor;

	/**
	 * @inheritDoc
	 */
	public function __construct( IContextSource $context ) {
		parent::__construct( 'mentorship', $context );
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeader() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-mentorship-header' )
			->params( $this->getContext()->getUser()->getName() )
			->escaped();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return implode( "\n", [
			$this->getMentorUserLink(),
			$this->getMentorInfo(),
			$this->getIntroText(),
			$this->getQuestionButton(),
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
		return 'oojs-ui.styles.icons-user';
	}

	/**
	 * @inheritDoc
	 */
	protected function getModules() {
		return 'ext.growthExperiments.Homepage.Mentorship';
	}

	/**
	 * @inheritDoc
	 */
	protected function getJsConfigVars() {
		return [ 'GEHomepageMentorshipMentorName' => $this->getMentor()->getName() ] +
			HelpPanel::getUserEmailConfigVars( $this->getContext()->getUser() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getActionData() {
		return array_merge(
			parent::getActionData(),
			[
				'mentorEditCount' => $this->getMentor()->getEditCount(),
				'mentorLastActive' => $this->getMentor()->getLatestEditTimestamp(),
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function canRender() {
		return (bool)$this->getMentor();
	}

	private function getMentorUserLink() {
		$icon = new IconWidget( [ 'icon' => 'userAvatar' ] );
		$link = Html::rawElement(
			'a',
			[
				'href' => $this->getMentor()->getUserPage()->getLinkURL(),
				'data-link-id' => 'mentor-userpage',
			],
			$icon . Html::element(
				'span',
				[ 'class' => 'growthexperiments-homepage-mentorship-username' ],
				$this->getContext()->getLanguage()->embedBidi(
					$this->getMentor()->getName()
				)
			)
		);
		return Html::rawElement( 'div', [
			'class' => 'growthexperiments-homepage-mentorship-userlink'
		], $link );
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
		$user = $this->getContext()->getUser();
		$editTimestamp = new MWTimestamp( $this->getMentor()->getLatestEditTimestamp() );
		$editTimestamp->offsetForUser( $user );
		$editDate = $editTimestamp->format( 'Ymd' );

		$now = new MWTimestamp();
		$now->offsetForUser( $user );
		$timeDiff = $now->diff( $editTimestamp );

		$today = $now->format( 'Ymd' );
		$yesterday = $now->timestamp->sub( new DateInterval( 'P1D' ) )->format( 'Ymd' );

		if ( $editDate === $today ) {
			$text = $this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-mentor-active-today' )
				->params( $this->getMentor()->getName() )
				->text();
		} elseif ( $editDate === $yesterday ) {
			$text = $this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-mentor-active-yesterday' )
				->params( $this->getMentor()->getName() )
				->text();
		} else {
			$text = $this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-mentor-active-days-ago' )
				->params( $this->getMentor()->getName() )
				->numParams( $timeDiff->days )
				->text();
		}

		return Html::element( 'span', [
			'class' => 'growthexperiments-homepage-mentorship-lastactive'
		], $text );
	}

	private function getIntroText() {
		return Html::element(
			'div',
			[ 'class' => 'growthexperiments-homepage-mentorship-intro' ],
			$this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-intro' )
				->params( $this->getMentor()->getName() )
				->params( $this->getContext()->getUser()->getName() )
				->text()
		);
	}

	private function getQuestionButton() {
		return new ButtonWidget( [
			'id' => 'mw-ge-homepage-mentorship-cta',
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
			}
		}
		return $this->mentor;
	}
}
