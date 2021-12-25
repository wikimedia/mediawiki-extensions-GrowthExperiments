<?php

namespace GrowthExperiments\Specials;

use DerivativeContext;
use GrowthExperiments\ExperimentUserManager;
use GrowthExperiments\HomepageModule;
use GrowthExperiments\HomepageModules\Impact;
use GrowthExperiments\HomepageModules\SuggestedEdits;
use Html;
use MediaWiki\Extensions\PageViewInfo\PageViewService;
use SpecialPage;
use TitleFactory;
use User;
use Wikimedia\Rdbms\IDatabase;

class SpecialImpact extends SpecialPage {

	/**
	 * @var IDatabase
	 */
	private $dbr;

	/**
	 * @var PageViewService|null
	 */
	private $pageViewService;
	/**
	 * @var ExperimentUserManager
	 */
	private $experimentUserManager;

	/**
	 * @var TitleFactory
	 */
	private $titleFactory;

	/**
	 * SpecialImpact constructor.
	 * @param IDatabase $dbr
	 * @param ExperimentUserManager $experimentUserManager
	 * @param TitleFactory $titleFactory
	 * @param PageViewService|null $pageViewService
	 */
	public function __construct(
		IDatabase $dbr,
		ExperimentUserManager $experimentUserManager,
		TitleFactory $titleFactory,
		PageViewService $pageViewService = null
	) {
		parent::__construct( 'Impact' );
		$this->dbr = $dbr;
		$this->pageViewService = $pageViewService;
		$this->experimentUserManager = $experimentUserManager;
		$this->titleFactory = $titleFactory;
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
		$impact = new Impact(
			$context,
			$context->getConfig()->get( 'GEHomepageImpactModuleEnabled' ),
			$this->dbr,
			$this->experimentUserManager,
			[
				'isSuggestedEditsEnabled' => SuggestedEdits::isEnabled( $context ),
				'isSuggestedEditsActivated' => SuggestedEdits::isActivated( $context ),
			],
			$this->titleFactory,
			$this->pageViewService
		);
		$out->addHTML( $impact->render( HomepageModule::RENDER_DESKTOP ) );
	}
}
