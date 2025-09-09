<?php

namespace GrowthExperiments\LevelingUp;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;

/**
 * Echo notification for "Re-engage" (T400118)
 */
class EchoReEngagePresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'growthexperiments-get-started';
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->getMessageWithAgent( 'growthexperiments-levelingup-reengage-notification-header' );
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		// We don't include the secondary link in email, because it's the same as the primary link,
		// and it looks awkward in an email to have two buttons leading to the same thing.
		if ( $this->getDistributionType() === 'email' ) {
			return [];
		}
		return [ [
			'url' => $this->getSpecialHomepageUrl( 're-engage-secondary-link' ),
			'label' => $this->msg( 'growthexperiments-levelingup-reengage-notification-call-to-action-label' )->text(),
			'icon' => 'edit'
		] ];
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->getSpecialHomepageUrl( 're-engage-primary-link-' . $this->getDistributionType() ),
			'label' => $this->msg(
				'growthexperiments-levelingup-reengage-notification-call-to-action-label'
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
