<?php

namespace GrowthExperiments\LevelingUp;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;

/**
 * Echo notification for "Keep going" (T328288)
 */
class EchoKeepGoingBasePresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'growthexperiments-keep-going';
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->getMessageWithAgent( 'growthexperiments-levelingup-keepgoing-notification-header' )
			->params( $this->event->getExtra()['suggestededitcount'] );
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		// We don't include the secondary link in email, because it's the same as the primary link,
		// and it looks awkward in an email to have two buttons leading to the same thing.
		if ( $this->getDistributionType() === 'email' ) {
			return [];
		}
		return [ [
			'url' => $this->getSpecialHomepageUrl( 'keep-going-secondary-link' ),
			'label' => $this->getMessageWithAgent(
				'growthexperiments-levelingup-keepgoing-notification-call-to-action-label'
			)->text(),
			'icon' => 'edit',
		] ];
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->getSpecialHomepageUrl( 'keep-going-primary-link-' . $this->getDistributionType() ),
			'label' => $this->msg( 'growthexperiments-levelingup-keepgoing-notification-call-to-action-label' )->text(),
		];
	}

	protected function getSpecialHomepageUrl( string $source ): string {
		$title = $this->event->getTitle();
		$title->setFragment( '#/homepage/suggested-edits' );
		return $title->getCanonicalURL( [ 'source' => $source ] );
	}
}
