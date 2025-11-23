<?php

namespace GrowthExperiments;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\User\Hook\UserSendConfirmationMailHook;
use MediaWiki\User\User;

class ConfirmEmailHooks implements AuthChangeFormFieldsHook, UserSendConfirmationMailHook {

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
		if ( isset( $formDescriptor['email'] ) && !$config->get( 'EmailConfirmToEdit' ) ) {
			$formDescriptor['email']['label-message'] = 'growthexperiments-confirmemail-emailrecommended';
			$formDescriptor['email']['help-message'] = 'growthexperiments-confirmemail-emailhelp';
		}
	}

	/**
	 * Override confirmation email
	 * @param User $user
	 * @param array &$mail
	 * @param array $info
	 */
	public function onUserSendConfirmationMail( $user, &$mail, $info ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( $config->get( MainConfigNames::UserEmailConfirmationUseHTML ) ) {
			// Core's HTML version is in use, give it precedence
			return;
		}

		$lang = RequestContext::getMain()->getLanguage();
		// TODO different messages for different $info['type'] values?
		$textParams = [
			$info['ip'],
			$user->getName(),
			$info['confirmURL'],
			$lang->userTimeAndDate( $info['expiration'], $user ),
			$info['invalidateURL'],
		];
		$htmlParams = $textParams;
		$htmlParams[2] = Message::rawParam( Html::element( 'a',
			[
				'href' => $info['confirmURL'],
				'style' => 'background: #36C; color: white; padding: 0.45em 0.6em; font-weight: bold; ' .
					'text-align: center; text-decoration: none;',
			],
			wfMessage( 'growthexperiments-confirmemail-confirm-button' )->text()
		) );

		$services = MediaWikiServices::getInstance();

		$logoImage = Html::rawElement( 'p', [], Html::element(
			'img', [ 'src' => $services->getUrlUtils()->expand( $config->get( 'Logo' ), PROTO_CANONICAL ) ]
		) );

		$mail['subject'] = wfMessage( 'growthexperiments-confirmemail-confirm-subject' )->text();
		$mail['body'] = [
			'text' => wfMessage( 'growthexperiments-confirmemail-confirm-body-plaintext' )
				->params( $textParams )->text(),
			'html' => $logoImage . wfMessage( 'growthexperiments-confirmemail-confirm-body-html' )
				->params( $htmlParams )->parse(),
		];
	}
}
