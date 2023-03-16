<?php

namespace GrowthExperiments\MentorDashboard\PersonalizedPraise;

use EchoEventPresentationModel;
use SpecialPage;

class EchoNewPraiseworthyMenteesPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'growthexperiments-mentor';
	}

	/** @inheritDoc */
	protected function getHeaderMessageKey() {
		return 'growthexperiments-notification-header-new-praiseworthy-mentees';
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		return $this->msg( 'growthexperiments-notification-body-new-praiseworthy-mentees' );
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		$title = SpecialPage::getTitleFor( 'MentorDashboard' );
		return [
			'url' => $title->getCanonicalURL( [
				'source' => 'personalized-praise-notification-' . $this->getDistributionType()
			] ),
			'label' => $title->getText()
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		$title = SpecialPage::getTitleFor( 'MentorDashboard' );

		return [
			[
				'url' => $title->getCanonicalURL( [
					'source' => 'personalized-praise-notification-' . $this->getDistributionType()
				] ),
				'label' => $this->msg( 'growthexperiments-notification-secondary-link-new-praiseworthy-mentees' )
					->text()
			]
		];
	}
}
