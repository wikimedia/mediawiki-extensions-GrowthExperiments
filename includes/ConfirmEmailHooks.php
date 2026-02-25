<?php

declare( strict_types = 1 );

namespace GrowthExperiments;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\Title\TitleFactory;

class ConfirmEmailHooks implements AuthChangeFormFieldsHook {

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly FeatureManager $featureManager,
	) {
	}

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
		$context->getOutput()->addModuleStyles( 'ext.growthExperiments.Account.styles' );

		if ( $this->featureManager->shouldShowCreateAccountV1(
			$context->getUser(),
			$context->getSkin(),
		) ) {
			$context->getOutput()->addJsConfigVars( 'GECreateAccountExperimentV1', true );
			if ( isset( $formDescriptor['email'] ) ) {
				$formDescriptor['email']['description-message'] = 'growthexperiments-confirmemail-emaildescription';
				$formDescriptor['email']['show-optional-flag'] = true;
				$formDescriptor['email']['optional-message'] = 'growthexperiments-confirmemail-email-recommended-flag';
				$formDescriptor['email']['label-message'] = 'growthexperiments-confirmemail-email-optional';
			}
			$formDescriptor['createaccount']['size'] = 'large';

			$formDescriptor['username']['label-message'] = 'userlogin-yourname';
			$formDescriptor['username']['description-message'] =
				'growthexperiments-createacct-username-description';
			if ( !$context->msg( 'createacct-helpusername-url' )->isDisabled() ) {
				$learnMoreLinkText = $context->msg(
					'growthexperiments-createacct-username-learn-more',
				)->parse();
				$learnMoreTitleText = $context->msg( 'createacct-helpusername-url' )->parse();
				$learnMoreTitle = $this->titleFactory->newFromDBkey( $learnMoreTitleText );
				if ( $learnMoreTitle->isKnown() ) {
					$learnMoreUrl = $learnMoreTitle->getCanonicalURL();
					$linkHtml = Html::rawElement(
						'a',
						[ 'href' => $learnMoreUrl, 'target' => '_blank' ],
						$learnMoreLinkText,
					);
					$description = $context->msg( 'growthexperiments-createacct-username-description' )->parse();
					$mergedHtml = implode( $context->msg( 'word-separator' )->escaped(), [ $description, $linkHtml ] );

					unset( $formDescriptor['username']['description-message'] );
					$formDescriptor['username']['description-raw'] = $mergedHtml;
				}
			}
		} elseif ( isset( $formDescriptor['email'] ) && !$config->get( MainConfigNames::EmailConfirmToEdit ) ) {
			// If email field exists on the form, change email label from "(optional)" to "
			// (recommended)", but only if email is optional

			$formDescriptor['email']['label-message'] = 'growthexperiments-confirmemail-emailrecommended';
			$formDescriptor['email']['help-message'] = 'growthexperiments-confirmemail-emailhelp';
		}
	}
}
