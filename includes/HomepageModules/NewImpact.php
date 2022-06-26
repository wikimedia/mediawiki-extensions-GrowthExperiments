<?php

namespace GrowthExperiments\HomepageModules;

use Config;
use GrowthExperiments\ExperimentUserManager;
use IContextSource;

/**
 * Class for the new Impact module.
 */
class NewImpact extends BaseModule {

	/** @inheritDoc */
	public function __construct(
		IContextSource $ctx,
		Config $wikiConfig,
		ExperimentUserManager $experimentUserManager
	) {
		parent::__construct( 'new-impact', $ctx, $wikiConfig, $experimentUserManager );
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
		return '<div></div>';
	}

	/** @inheritDoc */
	protected function getMobileSummaryBody() {
		return '<div></div>';
	}

	/** @inheritDoc */
	protected function getHeaderIconName() {
		return 'chart';
	}
}
