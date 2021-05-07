<?php

namespace MediaWiki\Extension\VisualEditor;

use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;

interface VisualEditorApiVisualEditorEditPostSaveHook {

	/**
	 * @param ProperPageIdentity $page
	 * @param UserIdentity $user
	 * @param string $wikitext
	 * @param array $params
	 * @param array $pluginData
	 * @param array $saveResult
	 * @param array &$apiResponse
	 * @return void
	 */
	public function onVisualEditorApiVisualEditorEditPostSave(
		ProperPageIdentity $page,
		UserIdentity $user,
		string $wikitext,
		array $params,
		array $pluginData,
		array $saveResult,
		array &$apiResponse
	): void;

}
