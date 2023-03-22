<?php

namespace GrowthExperiments\Mentorship;

use EchoEventPresentationModel;

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
		if ( $this->event->getExtra()['reason'] !== '' ) {
			return $this->msg( 'growthexperiments-notification-body-mentor-change' )
				->params( $this->event->getExtra()['reason'] );
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryLink() {
		return [
			'url' => $this->event->getTitle()->getLocalURL(),
			'label' => $this->msg( 'growthexperiments-notification-learn-more' )
					->params( $this->getUser()->getName() )
					->params( $this->event->getAgent()->getName() )
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
				'label' => $sayHi
			]
		];
	}

}
