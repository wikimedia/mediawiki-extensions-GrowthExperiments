<?php

namespace GrowthExperiments\LevelingUp;

use EchoEventPresentationModel;

/**
 * Echo notification for "Get Started" (T322435)
 */
class EchoGetStartedPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'growthexperiments-get-started';
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->getMessageWithAgent( 'growthexperiments-levelingup-getstarted-notification-header' );
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		return $this->getMessageWithAgent( 'growthexperiments-levelingup-getstarted-notification-body' );
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		return [ [
			// TODO: make dynamic
			'url' => 'https://www.mediawiki.org/wiki/Help:Growth/Tools/Suggested_edits',
			'label' => $this->msg( 'growthexperiments-levelingup-getstarted-notification-learnmore-label' ),
			'icon' => 'info'
		] ];
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->getSpecialHomepageUrl( 'get-started-primary-link-' . $this->getDistributionType() ),
			'label' => $this->msg(
				'growthexperiments-levelingup-getstarted-notification-call-to-action-label'
			)->text(),
			'icon' => 'edit'
		];
	}

	private function getSpecialHomepageUrl( string $source ): string {
		$title = $this->event->getTitle();
		$title->setFragment( '#/homepage/suggested-edits' );
		return $title->getCanonicalURL( [ 'source' => $source ] );
	}
}
