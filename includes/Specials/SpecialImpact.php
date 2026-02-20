<?php

namespace GrowthExperiments\Specials;

use GrowthExperiments\DashboardModule\IDashboardModule;
use GrowthExperiments\Homepage\HomepageModuleRegistry;
use GrowthExperiments\HomepageModules\Impact;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;

class SpecialImpact extends SpecialPage {

	private UserFactory $userFactory;
	private UserNameUtils $userNameUtils;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private HomepageModuleRegistry $homepageModuleRegistry;

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
		return $this->msg( 'growthexperiments-specialimpact-title' );
	}

	/**
	 * @inheritDoc
	 */
	public function isIncludable(): bool {
		return true;
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
		// Error out in the following scenarios:
		// If we don't have a user (logged-in or from argument) then error out.
		// If the impact user is hidden and the requesting does not have the permission to see it.
		// If the page is being included and the user is hidden since it will get cached and users without
		// the hideuser permission could get the cached result.
		if ( !$impactUser || !$impactUser->getId() ||
			( $impactUser->isHidden() &&
				( !$this->getAuthority()->isAllowed( 'hideuser' ) || $this->including() ) )
		) {
			$out->addHTML( Html::element( 'p', [ 'class' => 'error' ], $this->msg(
				'growthexperiments-specialimpact-invalid-username'
			)->text() ) );
			return;
		}
		// If the page is included and no user parameter ($par) is informed error out to prevent misunderstandings of
		// {{Special:Impact}} usage.
		if ( $this->including() && !$par ) {
			$out->addHTML( Html::element( 'p', [ 'class' => 'error' ], $this->msg(
				'growthexperiments-specialimpact-invalid-inclusion-without-username'
			)->text() ) );
			return;
		}
		$out->enableOOUI();
		$impact = $this->homepageModuleRegistry->get( 'impact', $this->getContext() );
		// If an argument was supplied and passed user validation, set the relevant user to the informed by.
		if ( $par && $impact instanceof Impact ) {
			$impact->setUserDataIsFor( $impactUser );
		}
		$configVarName = 'specialimpact';
		if ( $this->including() ) {
			$configVarName .= ':included';
		}
		$configVars = [
			'wgGEDisableLogging' => true,
		];
		$configVars[$configVarName] = [
			// Load the impact data from the client when the page is included and the user has edits, so we don't need
			// to reduce the expiry of the page in the parser cache.
			'impact' => $this->including() && $impactUser->getEditCount() ?
				null :
				$impact->getJsData( IDashboardModule::RENDER_DESKTOP ),
		];

		$out->addJsConfigVars( $configVars );
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
