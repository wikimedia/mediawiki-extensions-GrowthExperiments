<?php

namespace GrowthExperiments\Config\Providers;

use MediaWiki\Extension\CommunityConfiguration\Provider\DataProvider;
use StatusValue;
use stdClass;

class SuggestedEditsConfigProvider extends DataProvider {

	private const MAP_TASKS_PER_GROUP = [
		'copyedit' => 'easy',
		'links' => 'easy',
		'references' => 'medium',
		'update' => 'medium',
		'expand' => 'hard',
		'section_image_recommendation' => 'medium',
		'image_recommendation' => 'medium',
		'link_recommendation' => 'easy',
	];
	private const DEFAULT_GROUP = 'unknown';

	private const MAP_TASK_TYPES = [
		'image_recommendation' => 'image-recommendation',
		'section_image_recommendation' => 'section-image-recommendation',
		'link_recommendation' => 'link-recommendation',
	];
	private const DEFAULT_TASK_TYPE = 'template-based';

	public function loadForNewcomerTasks(): StatusValue {
		$result = $this->loadValidConfiguration();
		if ( $result->isOK() ) {
			$data = $result->getValue();
			unset( $data->GEInfoboxTemplates );

			// Priorly 'group' and 'type' were added using IConfigurationProvider::addAutocomputedProperties
			// but that results in these being written in config pages when running migrateConfig.php
			foreach ( $data as $taskId => $taskData ) {
				if ( !$taskData instanceof stdClass ) {
					continue;
				}
				$data->$taskId->group = self::MAP_TASKS_PER_GROUP[$taskId] ?? self::DEFAULT_GROUP;
				$data->$taskId->type = self::MAP_TASK_TYPES[$taskId] ?? self::DEFAULT_TASK_TYPE;
			}

			foreach ( $data as $key => $value ) {
				if ( str_contains( $key, '_' ) ) {
					$newKey = str_replace( '_', '-', $key );
					$data->$newKey = $value;
					unset( $data->$key );
				}
			}

			$result->setResult( true, $data );
		}
		return $result;
	}
}
