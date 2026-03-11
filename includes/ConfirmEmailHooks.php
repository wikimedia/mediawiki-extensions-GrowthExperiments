<?php

namespace GrowthExperiments;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;

class ConfirmEmailHooks implements AuthChangeFormFieldsHook {

	/**
	 * AuthChangeFormFields hook
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor HTMLForm form descriptor
	 * @param string $action
	 */
	public function onAuthChangeFormFields(
		$requests, $fieldInfo, &$formDescriptor, $action
	) {
		if ( !in_array( $action, [
			AuthManager::ACTION_CREATE,
			AuthManager::ACTION_CREATE_CONTINUE,
		] ) ) {
			return;
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$context = RequestContext::getMain();

		// Load JS that displays a message informing the user that a verification email is coming
		$context->getOutput()->addModules( 'ext.growthExperiments.Account' );
		$context->getOutput()->addJsConfigVars( 'confirmemail', true );

		// If email field exists on the form, change email label from "(optional)" to "
		// (recommended)", but only if email is optional
		if ( isset( $formDescriptor['email'] ) && !$config->get( MainConfigNames::EmailConfirmToEdit ) ) {
			$formDescriptor['email']['label-message'] = 'growthexperiments-confirmemail-emailrecommended';
			$formDescriptor['email']['help-message'] = 'growthexperiments-confirmemail-emailhelp';
		}
	}
}
