var AddLinkMobileArticleTarget = require( './AddLinkMobileArticleTarget.js' ),
	addlinkClasses = require( 'ext.growthExperiments.AddLink' );

ve.dm.modelRegistry.register( addlinkClasses.DMRecommendedLinkAnnotation );
ve.ce.annotationFactory.register( addlinkClasses.CERecommendedLinkAnnotation );
ve.dm.modelRegistry.register( addlinkClasses.DMRecommendedLinkErrorAnnotation );
ve.ce.annotationFactory.register( addlinkClasses.CERecommendedLinkErrorAnnotation );
ve.ui.contextItemFactory.register( addlinkClasses.RecommendedLinkContextItem );

// HACK: Override the registration of MobileArticleTarget for 'wikitext'
ve.init.mw.targetFactory.register( AddLinkMobileArticleTarget );
