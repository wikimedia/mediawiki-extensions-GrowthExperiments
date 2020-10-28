<?php

namespace GrowthExperiments\HomepageModules\SuggestedEditsComponents;

use OOUI\Tag;
use OOUI\Widget;

class ErrorCardWidget extends Widget {

	/** @inheritDoc */
	public function __construct( array $config = [] ) {
		parent::__construct( $config );
		/** @var \MessageLocalizer $localizer */
		$localizer = $config['localizer'];
		$this->appendContent(
			( new Tag( 'div' ) )->addClasses( [ 'se-card-error' ] )
				->appendContent(
					( new Tag( 'h3' ) )->addClasses( [ 'se-card-title' ] )
						->appendContent(
							$localizer->msg( 'growthexperiments-homepage-suggestededits-error-title' )->text()
						),
					( new Tag( 'div' ) )->addClasses( [ 'se-card-image' ] ),
					( new Tag( 'p' ) )->addClasses( [ 'se-card-text' ] )
						->appendContent(
							$localizer->msg( 'growthexperiments-homepage-suggestededits-error-description' )->text()
						)
				)
		);
	}
}
