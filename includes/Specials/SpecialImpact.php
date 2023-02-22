<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\HomepageModules\NewImpact;
use Html;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use SpecialPage;

class SpecialImpact extends SpecialPage {

	private UserFactory $userFactory;
	private UserNameUtils $userNameUtils;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private HomepageModuleRegistry $homepageModuleRegistry;

	/**
	 * @param UserFactory $userFactory
	 * @param UserNameUtils $userNameUtils
	 * @param UserNamePrefixSearch $userNamePrefixSearch
	 * @param HomepageModuleRegistry $homepageModuleRegistry
	 */
	public function __construct(
		UserFactory $userFactory,
		UserNameUtils $userNameUtils,
		UserNamePrefixSearch $userNamePrefixSearch,
		HomepageModuleRegistry $homepageModuleRegistry
	) {
		parent::__construct( 'Impact' );
		$this->userFactory = $userFactory;
		$this->userNameUtils = $userNameUtils;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
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
		$impact = $this->homepageModuleRegistry->get( 'impact', $this->getContext() );
		// If an argument was supplied and passed user validation, set the relevant user to the informed by.
		if ( $par && $impact instanceof NewImpact ) {
			$impact->setUserDataIsFor( $impactUser );
		}
		$out->addJsConfigVars( 'specialimpact', [
			'impact' => $impact->getJsData( IDashboardModule::RENDER_DESKTOP )
		] );
		$out->addHTML( $impact->render( IDashboardModule::RENDER_DESKTOP ) );
	}

	/** @inheritDoc */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$search = $this->userNameUtils->getCanonical( $search );
		if ( !$search ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return $this->userNamePrefixSearch
			->search( UserNamePrefixSearch::AUDIENCE_PUBLIC, $search, $limit, $offset );
	}
}
