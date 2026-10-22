<?php

declare( strict_types = 1 );

namespace GrowthExperiments\Specials;

use GrowthExperiments\AccountSetup\AccountSetupHooks;
use GrowthExperiments\FeatureManager;
use GrowthExperiments\NewcomerTasks\NewcomerTasksUserOptionsLookup;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;

class SpecialAccountSetup extends UnlistedSpecialPage {

	public function __construct(
		private readonly FeatureManager $featureManager,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly NewcomerTasksUserOptionsLookup $newcomerTasksUserOptionsLookup,
	) {
		parent::__construct( 'AccountSetup' );
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$this->requireNamedUser();
		$this->setHeaders();
		$user = $this->getUser();
		$out = $this->getOutput();

		if (
			!$this->featureManager->isHomeEnabledForUser( $user ) ||
			$this->userOptionsLookup->getOption( $user, AccountSetupHooks::ACCOUNT_SETUP_MOTIVATION_PROP ) !== null
		) {
			$out->redirect( SpecialPage::getTitleFor( 'Home' )->getFullURL() );
			return;
		}

		$out->addJsConfigVars( [
			'wgGEAccountSetupOnHome' => true,
			'wgGEInterestArticles' => $this->newcomerTasksUserOptionsLookup->getValidatedInterests( $user ),
			'wgGENewcomerTasksMaxInterestsForQueries' =>
				$this->getConfig()->get( 'GENewcomerTasksMaxInterestsForQueries' ),
		] );
		$out->addBodyClasses( 'growthexperiments-account-setup-page' );
		$out->addModuleStyles( 'ext.growthExperiments.AccountSetup.styles' );
		$out->addModules( 'ext.growthExperiments.AccountSetup' );
		$out->addHTML( Html::element( 'div', [ 'id' => 'growthexperiments-account_setup' ] ) );
	}
}
