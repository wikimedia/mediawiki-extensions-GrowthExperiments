<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use Html;

class MenteeOverviewVue extends MenteeOverview {
	/**
	 * @inheritDoc
	 */
	protected function getBody() {
		return Html::rawElement(
			'div',
			[
				'id' => 'vue-root',
				'class' => 'growthexperiments-mentor-dashboard-module-mentee-overview-vue-content'
			],
			Html::element(
				'p',
				[ 'class' => 'growthexperiments-mentor-dashboard-no-js-fallback' ],
				$this->msg( 'growthexperiments-mentor-dashboard-mentee-overview-no-js-fallback' )->text()
			)
		);
	}
}