<?php

namespace GrowthExperiments\HomepageModules\SuggestedEditsComponents;

use OOUI\Tag;
use OOUI\Widget;

class NoResultsCardWidget extends Widget {

	/**
	 * @param array $config Configuration options
	 *   - MessageLocalizer $config['localizer']
	 *   - bool $config['topicMatching'] Whether topic matching is enabled
	 *   - any option understood by Widget
	 */
	public function __construct( array $config = [] ) {
		parent::__construct( $config );
		/** @var \MessageLocalizer $localizer */
		$localizer = $config['localizer'];
		$noResultsDescriptionText = 'growthexperiments-homepage-suggestededits-select-other-difficulty';
		if ( $config['topicMatching'] ) {
			$noResultsDescriptionText = 'growthexperiments-homepage-suggestededits-select-other-topics-difficulty';
			if ( $config['topicMatchModeIsAND'] ) {
				$noResultsDescriptionText = 'growthexperiments-homepage-suggestededits-select-other-topic-mode';
			}
		}
		$this->appendContent(
			( new Tag( 'div' ) )->addClasses( [ 'se-card-no-results' ] )
			->appendContent(
				( new Tag( 'h3' ) )->addClasses( [ 'se-card-title' ] )
				->appendContent( $localizer->msg( 'growthexperiments-homepage-suggestededits-no-results' )->text() ),
				( new Tag( 'div' ) )->addClasses( [ 'se-card-image' ] ),
				( new Tag( 'p' ) )->addClasses( [ 'se-card-text' ] )
					->appendContent( $localizer->msg( $noResultsDescriptionText )->text() )
			)
		);
	}
}
