<?php

namespace MediaWiki\Extension\VisualEditor;

use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\User\UserIdentity;

interface VisualEditorApiVisualEditorEditPreSaveHook {

	/**
	 * This hook is executed in calls to ApiVisualEditorEdit using action=save, before the save is attempted.
	 *
	 * This hook can abort the save attempt by returning false.
	 *
	 * @param ProperPageIdentity $page The page identity of the title used in the save attempt.
	 * @param UserIdentity $user User associated with the save attempt.
	 * @param string $wikitext The wikitext used in the save attempt.
	 * @param array &$params The params passed by the client in the API request. See
	 *   ApiVisualEditorEdit::getAllowedParams()
	 * @param array $pluginData Associative array containing additional data specified by plugins, where the keys of
	 *   the array are plugin names and the value are arbitrary data.
	 * @param array &$apiResponse The modifiable API response that VisualEditor will return to the client.
	 *   Note: When returning false, the "message" key must be set to a valid i18n message key, e.g.
	 *     ```php
	 *     $apiResponse['message'] = [ 'growthexperiments-addlink-notinstore', $title->getPrefixedText() ];
	 *     return false;
	 * @return void|bool
	 *   False will abort the save attempt and return an error to the client. If false is returned, the implementer
	 *   must also set the "message" key in $apiResponse for rendering the error response to the client.
	 */
	public function onVisualEditorApiVisualEditorEditPreSave(
		ProperPageIdentity $page,
		UserIdentity $user,
		string $wikitext,
		array &$params,
		array $pluginData,
		array &$apiResponse
	);

}
