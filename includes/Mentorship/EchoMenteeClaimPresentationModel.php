<?php

namespace GrowthExperiments\Mentorship;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;

class EchoMenteeClaimPresentationModel extends EchoEventPresentationModel {

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
		return $this->getMessageWithAgent( 'growthexperiments-notification-header-mentee-claimed' )
			->params( $this->event->getTitle()->getText() );
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyMessage() {
		if ( $this->event->getExtra()['reason'] !== '' ) {
			return $this->msg( 'growthexperiments-notification-body-mentee-claimed' )
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
			'label' => $this->event->getTitle()->getText(),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		return [
			[
				'url' => 'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:Growth/Tools/How_to_claim_a_mentee',
				'label' => $this->msg(
					'growthexperiments-notification-secondary-link-label-mentee-claimed'
				)->text(),
			],
		];
	}
}
