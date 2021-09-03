<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use Html;

class MenteeOverview extends BaseModule {
	/**
	 * @inheritDoc
	 */
	protected function getHeaderText() {
		return $this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-headline' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheader() {
		return $this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-intro' )->text();
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubheaderTag() {
		return 'p';
	}

	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return Html::rawElement(
			'div',
			[
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-content'
			],
			Html::element(
				'p',
				[ 'class' => 'growthexperiments-mentor-dashboard-no-js-fallback' ],
				$this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-no-js-fallback' )->text()
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getMobileSummaryBody() {
		return $this->getBody();
	}
}
