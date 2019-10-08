<?php

use GrowthExperiments\NewcomerTasks\TaskSuggester\ErrorForwardingTaskSuggester;
use GrowthExperiments\NewcomerTasks\TaskSuggester\TaskSuggester;
use MediaWiki\MediaWikiServices;

return [

	'GrowthExperimentsEditSuggester' => function ( MediaWikiServices $services ): TaskSuggester {
		return new ErrorForwardingTaskSuggester( StatusValue::newFatal( new ApiRawMessage(
			'The EditSuggester has not been configured! See StaticTaskSuggester.',
			'tasksuggester-not-configured'
		) ) );
	},

];
