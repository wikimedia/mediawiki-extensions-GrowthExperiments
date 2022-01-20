<?php

namespace GrowthExperiments;

use Html;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;
use Message;
use RequestContext;
use User;

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
		$context->getOutput()->addModules( 'ext.growthExperiments.Account' );
		$context->getOutput()->addJsConfigVars( 'confirmemail', true );

		// If email field exists on the form, change email label from "(optional)" to "
		// (recommended)", but only if email is optional
		if ( isset( $formDescriptor['email'] ) && !$config->get( 'EmailConfirmToEdit' ) ) {
			$formDescriptor['email']['label-message'] = 'growthexperiments-confirmemail-emailrecommended';
			$formDescriptor['email']['help-message'] = 'growthexperiments-confirmemail-emailhelp';
			// helpInline doesn't work because this form hasn't been converted to OOUI yet (T85853)
			$formDescriptor['email']['helpInline'] = true;
		}

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
			$formDescriptor['captchaWord']['cssclass'] =
				( $formDescriptor['capthaWord']['cssclass'] ?? '' ) . ' mw-ge-confirmemail-captcha';

			$context->getOutput()->addModuleStyles(
				'ext.growthExperiments.Account.styles'
			);
		}
	}

	/**
	 * Override confirmation email
	 * @param User $user
	 * @param array &$mail
	 * @param array $info
	 */
	public static function onUserSendConfirmationMail( User $user, array &$mail, array $info ) {
		if ( !self::isConfirmEmailEnabled() ) {
			return;
		}
		$lang = RequestContext::getMain()->getLanguage();
		$config = MediaWikiServices::getInstance()->getMainConfig();

		// TODO different messages for different $info['type'] values?
		$textParams = [
			$info['ip'],
			$user->getName(),
			$info['confirmURL'],
			$lang->userTimeAndDate( $info['expiration'], $user ),
			$info['invalidateURL']
		];
		$htmlParams = $textParams;
		$htmlParams[2] = Message::rawParam( Html::element( 'a',
			[
				'href' => $info['confirmURL'],
				'style' => 'background: #36C; color: white; padding: 0.45em 0.6em; font-weight: bold; ' .
					'text-align: center; text-decoration: none;'
			],
			wfMessage( 'growthexperiments-confirmemail-confirm-button' )->text()
		) );
		$logoImage = Html::rawElement( 'p', [],
			Html::element( 'img', [ 'src' => wfExpandUrl( $config->get( 'Logo' ), PROTO_CANONICAL ) ] )
		);

		$mail['subject'] = wfMessage( 'growthexperiments-confirmemail-confirm-subject' )->text();
		$mail['body'] = [
			'text' => wfMessage( 'growthexperiments-confirmemail-confirm-body-plaintext' )
				->params( $textParams )->text(),
			'html' => $logoImage . wfMessage( 'growthexperiments-confirmemail-confirm-body-html' )
				->params( $htmlParams )->parse()
		];
	}
}
