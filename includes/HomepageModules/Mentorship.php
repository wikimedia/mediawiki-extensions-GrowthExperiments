<?php

namespace GrowthExperiments\HomepageModules;

use ConfigException;
use GrowthExperiments\Mentor;
use Html;
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
class Mentorship extends BaseSidebarModule {

	const MENTORSHIP_MODULE_QUESTION_TAG = 'mentorship module question';

	/** @var User */
	private $mentor;

	public function __construct() {
		parent::__construct( 'mentorship' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getHeader() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-mentorship-header' )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return implode( "\n", [
			$this->getMentorUserLink(),
			$this->getEditCount(),
			$this->getLastActive(),
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
		return [ 'GEHomepageMentorshipMentorName' => $this->getMentor()->getName() ];
	}

	/**
	 * @inheritDoc
	 */
	protected function canRender() {
		return (bool)$this->getMentor();
	}

	private function getMentorUserLink() {
		$icon = new IconWidget( [ 'icon' => 'userAvatar' ] );
		return Html::rawElement(
			'a',
			[
				'href' => $this->getMentor()->getUserPage()->getLinkURL(),
			],
			$icon . $this->getMentor()->getName()
		);
	}

	private function getEditCount() {
		return Html::element(
			'div',
			[],
			$this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-mentor-edits' )
				->numParams( $this->getMentor()->getEditCount() )
				->text()
		);
	}

	private function getLastActive() {
		$user = $this->getContext()->getUser();
		$editTimestamp = new MWTimestamp( $this->getMentor()->getLatestEditTimestamp() );
		$editTimestamp->offsetForUser( $user );
		$now = new MWTimestamp();
		$now->offsetForUser( $user );
		$timeDiff = $now->diff( $editTimestamp );

		if ( $timeDiff->days === 0 ) {
			$text = $this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-mentor-active-today' )
				->params( $this->getMentor()->getName() )
				->text();
		} elseif ( $timeDiff->days === 1 ) {
			$text = $this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-mentor-active-yesterday' )
				->params( $this->getMentor()->getName() )
				->text();
		} else {
			$text = $this->getContext()
				->msg( 'growthexperiments-homepage-mentorship-mentor-active-days-ago' )
				->params( $this->getMentor()->getName() )
				->numParams( (int)$timeDiff->format( '%d' ) )
				->text();
		}

		return Html::element( 'div', [], $text );
	}

	private function getIntroText() {
		return Html::element(
			'div',
			[],
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
	 * @return bool|User The current user's mentor (may be newly assigned)
	 * or false if none are available
	 * @throws ConfigException
	 * @throws \MWException
	 */
	private function getMentor() {
		if ( !$this->mentor ) {
			$mentor = Mentor::newFromMentee( $this->getContext()->getUser(), true );
			if ( $mentor ) {
				$this->mentor = $mentor->getMentorUser();
			}
		}
		return $this->mentor;
	}
}
