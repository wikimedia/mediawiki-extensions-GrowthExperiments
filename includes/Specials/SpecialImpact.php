<?php

namespace GrowthExperiments\Specials;

use DerivativeContext;
use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use Html;
use MediaWiki\User\UserFactory;
use SpecialPage;

class SpecialImpact extends SpecialPage {

	private UserFactory $userFactory;
	private HomepageModuleRegistry $homepageModuleRegistry;

	/**
	 * @param UserFactory $userFactory
	 * @param HomepageModuleRegistry $homepageModuleRegistry
	 */
	public function __construct(
		UserFactory $userFactory,
		HomepageModuleRegistry $homepageModuleRegistry
	) {
		parent::__construct( 'Impact' );
		$this->userFactory = $userFactory;
		$this->homepageModuleRegistry = $homepageModuleRegistry;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'growth-tools';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'growthexperiments-specialimpact-title' )->text();
	}

	/**
	 * Render the impact module in following conditions:
	 *
	 * - user is logged out, $par must be a valid username
	 * - user is logged-in, $par is not set
	 * - user is logged-in, $par is set to a valid username
	 *
	 * Error if:
	 *
	 * - user is logged-in, $par is set to an invalid username
	 * - user is logged-out and $par is not supplied
	 *
	 * @param string|null $par
	 * @return void
	 */
	public function execute( $par ) {
		parent::execute( $par );
		$impactUser = $this->getUser();
		// If an argument was supplied, attempt to load a user.
		if ( $par ) {
			$impactUser = $this->userFactory->newFromName( $par );
		}
		$out = $this->getContext()->getOutput();
		// If we don't have a user (logged-in or from argument) then error out.
		if ( !$impactUser || !$impactUser->getId() ||
			( $impactUser->isHidden() && !$this->getAuthority()->isAllowed( 'hideuser' ) )
		) {
			$out->addHTML( Html::element( 'p', [ 'class' => 'error' ], $this->msg(
				'growthexperiments-specialimpact-invalid-username'
			)->text() ) );
			return;
		}
		$out->enableOOUI();
		// Use a derivative context as we might be modifying the user.
		$context = new DerivativeContext( $this->getContext() );
		if ( !$impactUser->equals( $this->getUser() ) ) {
			// Add warning if viewing someone else's impact data.
			$out->addHTML(
				Html::element( 'p', [ 'class' => 'warning' ],
					$this->msg(
					'growthexperiments-specialimpact-showing-for-other-user'
					)->plaintextParams( $impactUser->getName() )
				->text() ) );
		}
		$context->setUser( $impactUser );
		$impact = $this->homepageModuleRegistry->get( 'impact', $context );
		$out->addJsConfigVars( 'specialimpact', [
			'impact' => $impact->getJsData( IDashboardModule::RENDER_DESKTOP )
		] );
		$out->addHTML( $impact->render( IDashboardModule::RENDER_DESKTOP ) );
	}
}
