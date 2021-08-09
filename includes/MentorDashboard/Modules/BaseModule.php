<?php

namespace GrowthExperiments\MentorDashboard\Modules;

use GrowthExperiments\DashboardModule\DashboardModule;

abstract class BaseModule extends DashboardModule {
	protected const BASE_CSS_CLASS = 'growthexperiments-mentor-dashboard-module';

	/**
	 * @inheritDoc
	 */
	protected function getHeaderIconName() {
		return 'info';
	}
}
