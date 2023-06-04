<?php

namespace GrowthExperiments\Hooks;

use MediaWiki\HookContainer\HookContainer;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements
	\MediaWiki\Hook\EditFilterMergedContentHook
{
	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onEditFilterMergedContent( $context, $content, $status,
		$summary, $user, $minoredit
	) {
		return $this->hookContainer->run(
			'EditFilterMergedContent',
			[ $context, $content, $status, $summary, $user, $minoredit ]
		);
	}
}
