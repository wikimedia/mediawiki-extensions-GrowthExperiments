<?php

namespace GrowthExperiments;

use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;
use Html;
use RequestContext;

class ConfirmEmailHooks {

	/**
	 * @return bool Whether the email confirmation improvements are enabled
	 */
	public static function isConfirmEmailEnabled() {
		return MediaWikiServices::getInstance()->getMainConfig()->get( 'GEConfirmEmailEnabled' );
	}

	/**
	 * AuthChangeFormFields hook
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor HTMLForm form descriptor
	 * @param string $action
	 */
	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		if ( !self::isConfirmEmailEnabled() ) {
			return;
		}
		if ( !in_array( $action, [
			AuthManager::ACTION_CREATE,
			AuthManager::ACTION_CREATE_CONTINUE
		] ) ) {
			return;
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$context = RequestContext::getMain();

		// Load JS that displays a message informing the user that a verification email is coming
		$context->getOutput()->addModules( 'ext.growthExperiments.confirmEmail.createAccount' );

		// Change email label from "(optional)" to "(recommended)", but only if email is optional
		if ( !$config->get( 'EmailConfirmToEdit' ) ) {
			$formDescriptor['email']['label-message'] = 'growthexperiments-confirmemail-emailrecommended';
			$formDescriptor['email']['help-message'] = 'growthexperiments-confirmemail-emailhelp';
			// helpInline doesn't work because this form hasn't been converted to OOUI yet (T85853)
			$formDescriptor['email']['helpInline'] = true;
		}

		// @phan-suppress-next-line PhanTypeInvalidDimOffset
		if ( ( $formDescriptor['captchaWord']['class'] ?? null ) === 'HTMLFancyCaptchaField' ) {
			// Remove long-winded CAPTCHA explanation message
			unset( $formDescriptor['captchaWord']['label-message'] );

			// HACK: add "what's this" link by duplicating the entire label, then hiding
			// the original label with CSS. Unfortunately HTMLFancyCaptchaField doesn't let us change
			// the built-in label message or append to it, instead it prepends it in a <p>.
			$formDescriptor['captchaWord']['label-raw'] = Html::rawElement(
				'label',
				[ 'for' => 'mw-input-captchaWord' ],
				$context->msg( 'captcha-label' )->escaped() . ' ' .
					$context->msg( 'fancycaptcha-captcha' )->escaped() . ' ' .
					$context->msg( 'growthexperiments-confirmemail-captcha-help' )->parse()
			);
			$formDescriptor['captchaWord']['cssclass'] = 'mw-ge-confirmemail-captcha';

			$context->getOutput()->addModuleStyles(
				'ext.growthExperiments.confirmEmail.createAccount.styles'
			);
		}
	}
}
