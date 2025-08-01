<?php

namespace GrowthExperiments\LevelingUp;

/**
 * Echo notification for "Get Started" notification (second version, T400118)
 */
class EchoGetStartedPresentationModel extends EchoGetStartedBasePresentationModel {

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->getMessageWithAgent( 'growthexperiments-levelingup-getstarted-noedits-notification-header' );
	}
}
