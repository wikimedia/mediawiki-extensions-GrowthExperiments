<?php

declare( strict_types = 1 );

namespace GrowthExperiments;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Auth\Hook\LocalUserCreatedHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\TestKitchen\Sdk\OverriddenExperiment;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\Hook\CreateAccountShouldShowUsernamePolicyPopoverHook;
use MediaWiki\SpecialPage\LoginSignupSpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use Wikimedia\Stats\StatsFactory;

class ConfirmEmailHooks implements
	AuthChangeFormFieldsHook,
	AuthPreserveQueryParamsHook,
	LocalUserCreatedHook,
	CreateAccountShouldShowUsernamePolicyPopoverHook
{

	private const EXPERIMENT_GROUP_FORM_FIELD_NAME = 'we18-experiment-group';

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly FeatureManager $featureManager,
		private readonly Config $mainConfig,
		private readonly IExperimentManager $experimentManager,
		private readonly StatsFactory $statsFactory,
	) {
	}

	/**
	 * Opt in to the core Minerva username policy popover ("Choose carefully" opens a
	 * popover) for the account-creation form experiment v2.
	 *
	 * @param LoginSignupSpecialPage $specialPage
	 * @param bool &$show
	 */
	public function onCreateAccountShouldShowUsernamePolicyPopover(
		LoginSignupSpecialPage $specialPage,
		bool &$show
	): void {
		if ( $this->featureManager->shouldShowCreateAccountV2(
			$specialPage->getUser(),
			$specialPage->getSkin(),
			$specialPage->getRequest(),
		) ) {
			$show = true;
		}
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

		$this->recordBaseline( $context->getUser(), $context->getSkin() );
		$this->recordExperimentGroup( $context->getUser(), $context->getSkin(), $formDescriptor );
		$shouldShowV2 = $this->featureManager->shouldShowCreateAccountV2(
			$context->getUser(),
			$context->getSkin(),
			$context->getRequest(),
		);
		if ( $shouldShowV2 ) {
			// Send a hint for experiment styles to kick-in. Experiment styles:
			// - GrowthExperiments/modules/ext.growthExperiments.Account.styles/WE18ExperimentV1.less
			$context->getOutput()->addBodyClasses( [
				'we-1-8-account-creation-form-v2-treatment',
				'wiki-' . $config->get( 'DBname' ),
			] );
			$context->getOutput()->addJsConfigVars( 'GECreateAccountExperimentV2', $shouldShowV2 );
			$formDescriptor['createaccount']['size'] = 'large';
			if ( isset( $formDescriptor['password'] ) ) {
				$formDescriptor['password']['end-icon-class'] = 'growthexperiments-password-reveal-icon';
			}
			if ( isset( $formDescriptor['retype'] ) ) {
				$formDescriptor['retype']['end-icon-class'] = 'growthexperiments-password-reveal-icon';
			}
			// Default GrowthExperience, applies to unsampled and control
		} elseif ( isset( $formDescriptor['email'] ) && !$config->get( MainConfigNames::EmailConfirmToEdit ) ) {
			$formDescriptor['email']['label-message'] = 'growthexperiments-confirmemail-emailrecommended';
			$formDescriptor['email']['help-message'] = 'growthexperiments-confirmemail-emailhelp';
		}
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

	private function recordBaseline( ?User $user, Skin $skin ): void {
		if ( $user === null || $user->isAnon() ) {
			$userType = 'anon';
		} elseif ( $user->isTemp() ) {
			$userType = 'temp';
		} else {
			$userType = 'named';
		}
		$wikiName = $this->mainConfig->get( 'DBname' );
		$isMobile = Util::isMobile( $skin );

		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getCounter( 'baseline_account_creation_forms_opened_total' )
			->setLabel( 'wiki', $wikiName )
			->setLabel( 'platform', $isMobile ? 'mobile' : 'desktop' )
			->setLabel( 'usertype', $userType )
			->increment();
	}

	private function recordExperimentGroup( ?User $user, Skin $skin, array &$formDescriptor ): void {
		$isAnon = $user === null || $user->isAnon();
		$isMobile = Util::isMobile( $skin );
		$wikiName = $this->mainConfig->get( 'DBname' );
		$isEnWiki = $wikiName === 'enwiki';

		$isEligible = $isAnon && $isMobile && $isEnWiki;
		if ( !$isEligible ) {
			return;
		}

		if ( $this->experimentManager instanceof ExperimentTestKitchenManager ) {
			$experiment = $this->experimentManager->getExperiment(
				IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1
			);
			if ( $experiment instanceof OverriddenExperiment ) {
				return;
			}
		}

		$experimentGroup = $this->experimentManager->getAssignedGroup(
			IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1
		);
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getCounter( 'experiment_account_creation_forms_opened_total' )
			->setLabel( 'wiki', $wikiName )
			->setLabel( 'experiment', IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1 )
			->setLabel( 'group', $experimentGroup ?? 'unsampled' )
			->increment();

		if ( $experimentGroup === IExperimentManager::VARIANT_TREATMENT ) {
			$formDescriptor[self::EXPERIMENT_GROUP_FORM_FIELD_NAME] = [
				'type' => 'hidden',
				'name' => self::EXPERIMENT_GROUP_FORM_FIELD_NAME,
				'default' => 'treatment',
			];
		} elseif ( $experimentGroup === IExperimentManager::VARIANT_CONTROL ) {
			$formDescriptor[self::EXPERIMENT_GROUP_FORM_FIELD_NAME] = [
				'type' => 'hidden',
				'name' => self::EXPERIMENT_GROUP_FORM_FIELD_NAME,
				'default' => 'control',
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onLocalUserCreated( $user, $autocreated ): void {
		$experimentGroup = RequestContext::getMain()->getRequest()
			->getVal( self::EXPERIMENT_GROUP_FORM_FIELD_NAME, '' );
		if ( !$experimentGroup ) {
			return;
		}

		if ( $autocreated || $user->isTemp() ) {
			return;
		}

		$hasEmail = RequestContext::getMain()->getRequest()
			->getVal( 'email', '' ) !== '';
		$this->statsFactory->withComponent( 'GrowthExperiments' )
			->getCounter( 'experiment_account_creations_total' )
			->setLabel( 'wiki', $this->mainConfig->get( 'DBname' ) )
			->setLabel( 'experiment', IExperimentManager::ACCOUNT_CREATION_FORM_EXPERIMENT_V1 )
			->setLabel( 'group', $experimentGroup )
			->setLabel( 'hasEmail', $hasEmail ? 'Yes' : 'No' )
			->increment();
	}
}
