<?php

namespace GrowthExperiments\LevelingUp;

/**
 * Echo notification for "Keep going" notification (second version, T400118)
 */
class EchoKeepGoingPresentationModel extends EchoKeepGoingBasePresentationModel {

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->getMessageWithAgent( 'growthexperiments-levelingup-keepgoing-exploring-notification-header' );
	}
}
