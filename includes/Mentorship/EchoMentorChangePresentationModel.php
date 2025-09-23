<?php

namespace GrowthExperiments\Mentorship;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;

class EchoMentorChangePresentationModel extends EchoEventPresentationModel {

	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		if ( in_array( $this->language->getCode(), [ 'he', 'yi' ] ) ) {
			// T332732: In he, the mentor icon should be displayed in LTR
			return 'growthexperiments-mentor-ltr';
		}
		return 'growthexperiments-mentor';
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderMessage() {
		return $this->getMessageWithAgent( 'growthexperiments-notification-header-mentor-change' )
			->params( $this->getUser()->getName() );
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyMessage() {
		$extra = $this->event->getExtra();
		if ( array_key_exists( 'reason', $extra ) && $extra['reason'] !== '' ) {
			// B&C: pre-T327493 notification
			return $this->msg( 'growthexperiments-notification-body-mentor-change' )
				->params( $extra['reason'] );
		} elseif ( array_key_exists( 'oldMentor', $extra ) ) {
			return $this->msg( 'growthexperiments-notification-body-mentor-change-new' )
				->params( $extra['oldMentor'] );
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryLink() {
		return [
			'url' => $this->event->getAgent()->getUserPage()->getLocalURL(),
			'label' => $this->msg( 'growthexperiments-notification-learn-more' )
					->params( $this->getUser()->getName() )
					->params( $this->event->getAgent()->getName() ),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		$sayHi = $this->getMessageWithAgent( 'growthexperiments-notification-say-hi-mentor-change' )
			->params( $this->getUser()->getName() )->text();
		return [
			[
				'url' => $this->event->getAgent()->getTalkPage()->getLocalURL(),
				'label' => $sayHi,
			],
		];
	}

}
