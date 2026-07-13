<?php

declare( strict_types = 1 );

namespace GrowthExperiments;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;

class ConfirmEmailHooks implements
	AuthChangeFormFieldsHook,
	AuthPreserveQueryParamsHook
{

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

		$context = RequestContext::getMain();

		// Load JS that displays a message informing the user that a verification email is coming
		$context->getOutput()->addModules( 'ext.growthExperiments.Account' );
		$context->getOutput()->addModuleStyles( 'ext.growthExperiments.Account.styles' );
	}

	/**
	 * @inheritDoc
	 */
	public function onAuthPreserveQueryParams( array &$params, array $options ): void {
		$request = RequestContext::getMain()->getRequest();
		$experiments = $request->getArray( 'experiments' );
		if ( $experiments ) {
			$params['experiments'] = $experiments;
		}
	}

}
