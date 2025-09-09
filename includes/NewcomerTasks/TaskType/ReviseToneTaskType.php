<?php

declare( strict_types = 1 );

namespace GrowthExperiments\NewcomerTasks\TaskType;

class ReviseToneTaskType extends TaskType {

	protected const IS_MACHINE_SUGGESTION = true;

	public function shouldOpenInEditMode(): bool {
		return true;
	}

	public function getDefaultEditSection(): string {
		return 'all';
	}

	public function shouldShowHelpPanelQuickTips(): bool {
		return false;
	}

}
