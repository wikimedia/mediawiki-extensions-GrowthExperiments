<?php

namespace GrowthExperiments\Config\Providers;

use MediaWiki\Extension\CommunityConfiguration\Provider\DataProvider;
use stdClass;

class SuggestedEditsConfigProvider extends DataProvider {

	private const MAP_TASKS_PER_GROUP = [
		'copyedit' => 'easy',
		'links' => 'easy',
		'references' => 'medium',
		'section-image-recommendation' => 'medium',
		'update' => 'medium',
		'expand' => 'hard',
		'image-recommendation' => 'medium',
		'link-recommendation' => 'easy',
	];
	private const DEFAULT_GROUP = 'unknown';

	private const MAP_TASK_TYPES = [
		'image-recommendation' => 'image-recommendation',
		'section-image-recommendation' => 'section-image-recommendation',
		'link-recommendation' => 'link-recommendation',
	];
	private const DEFAULT_TASK_TYPE = 'template-based';

	/**
	 * @inheritDoc
	 */
	protected function addAutocomputedProperties( stdClass $config ): stdClass {
		foreach ( $config as $taskId => $taskData ) {
			if ( !$taskData instanceof stdClass ) {
				continue;
			}
			$config->$taskId->group = self::MAP_TASKS_PER_GROUP[$taskId] ?? self::DEFAULT_GROUP;
			$config->$taskId->type = self::MAP_TASK_TYPES[$taskId] ?? self::DEFAULT_TASK_TYPE;
		}
		return $config;
	}
}
