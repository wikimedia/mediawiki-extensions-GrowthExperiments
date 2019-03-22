<?php

namespace GrowthExperiments\Specials;

use DerivativeContext;
use GrowthExperiments\HomepageModules\Impact;
use Html;
use SpecialPage;
use User;

class SpecialImpact extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Impact' );
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
			$impactUser = User::newFromName( $par );
		}
		$out = $this->getContext()->getOutput();
		// If we don't have a user (logged-in or from argument) then error out.
		if ( !$impactUser || !$impactUser->getId() ) {
			$out->addHTML( HTML::element( 'p', [ 'class' => 'error' ], $this->msg(
				'growthexperiments-specialimpact-invalid-username'
			)->text() ) );
			return;
		}
		$out->enableOOUI();
		$impact = new Impact();
		// Use a derivative context as we might be modifying the user.
		$context = new DerivativeContext( $this->getContext() );
		if ( !$impactUser->equals( $this->getUser() ) ) {
			// Add warning if viewing someone else's impact data.
			$out->addHTML(
				HTML::element( 'p', [ 'class' => 'warning' ],
					$this->msg(
					'growthexperiments-specialimpact-showing-for-other-user'
					)->plaintextParams( $impactUser->getName() )
				->text() ) );
		}
		$context->setUser( $impactUser );
		$out->addHTML( $impact->render( $context ) );
	}
}
