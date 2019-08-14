<?php

namespace GrowthExperiments;

use EchoEventPresentationModel;

class EchoMentorChangePresentationModel extends EchoEventPresentationModel {

	/**
	 * @inheritDoc
	 */
	public function getIconType() {
		return 'growthexperiments-menteeclaimed';
	}

	/**
	 * @inheritDoc
	 */
	public function getHeaderMessage() {
		return $this->getMessageWithAgent( 'growthexperiments-notification-header-mentor-change' );
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
			'url' => $this->event->getTitle()->getLocalURL()
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		$sayHi = $this->getMessageWithAgent( 'growthexperiments-notification-say-hi-mentor-change' );
		return [
			[
				'url' => $this->event->getAgent()->getTalkPage()->getLocalURL(),
				'label' => $sayHi
			]
		];
	}

}
