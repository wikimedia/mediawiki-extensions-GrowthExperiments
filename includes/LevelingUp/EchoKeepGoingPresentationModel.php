<?php

namespace GrowthExperiments\LevelingUp;

/**
 * Echo notification for "Keep going" notification (second version, T400118). Event type
 * name is: "keep-going-exploring"
 */
class EchoKeepGoingPresentationModel extends EchoKeepGoingBasePresentationModel {

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->getMessageWithAgent( 'growthexperiments-levelingup-keepgoing-exploring-notification-header' );
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
				'growthexperiments-levelingup-keepgoing-exploring-notification-call-to-action-label'
			)->text(),
			'icon' => 'edit',
		] ];
	}
}
