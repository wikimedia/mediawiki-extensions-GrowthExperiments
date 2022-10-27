<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\ExperimentUserManager;
use Html;
use IContextSource;
use MediaWiki\User\UserIdentity;

/**
 * Class for the new Impact module.
 */
class NewImpact extends BaseModule {

	/** @var UserIdentity */
	private $userIdentity;

	/** @inheritDoc */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager,
		UserIdentity $userIdentity
	) {
		parent::__construct( 'impact', $ctx, $wikiConfig, $experimentUserManager );
		$this->userIdentity = $userIdentity;
	}

	/** @inheritDoc */
	protected function getJsConfigVars() {
		if ( !$this->userIdentity ) {
			return [];
		}
		return [
			'GENewImpactD3Enabled' => $this->getConfig()->get( 'GENewImpactD3Enabled' ),
			'GENewImpactRelevantUserName' => $this->userIdentity->getName(),
			'GENewImpactRelevantUserId' => $this->userIdentity->getId()
		];
	}

	/** @inheritDoc */
	protected function getHeaderText() {
		return $this->getContext()
			->msg( 'growthexperiments-homepage-new-impact-header' )
			->params( $this->getContext()->getUser()->getName() )
			->text();
	}

	/** @inheritDoc */
	protected function getBody() {
		return Html::rawElement( 'div',
				[ 'id' => 'new-impact-vue-root' ]
			) .
			Html::element( 'p',
				[ 'class' => 'growthexperiments-homepage-new-impact-no-js-fallback' ],
				$this->msg( 'growthexperiments-homepage-new-impact-no-js-fallback' )->text()
			);
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return '<div></div>';
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'chart';
	}

	/** @inheritDoc */
	protected function getModules() {
		return [ 'ext.growthExperiments.Homepage.NewImpact' ];
	}
}
