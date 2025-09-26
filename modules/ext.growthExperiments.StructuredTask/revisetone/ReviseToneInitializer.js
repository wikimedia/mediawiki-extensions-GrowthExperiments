const suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();
const GrowthSuggestionToneCheck = require( './GrowthSuggestionToneCheck.js' );
const simpleLevenshtein = require( '../../utils/SimpleLevenshtein.js' );

class ReviseToneInitializer {

	// TODO: use private class fields once Grade A support is raised to at least Safari 15, see T395347
	// #taskData;

	constructor() {
		this.taskData = suggestedEditSession.taskData;
	}

	initialize() {
		mw.editcheck.editCheckFactory.unregister( mw.editcheck.ToneCheck );
		mw.editcheck.editCheckFactory.register( GrowthSuggestionToneCheck, GrowthSuggestionToneCheck.static.name );
		mw.hook( 'growthExperiments.structuredTask.onboardingCompleted' ).add(
			() => {
				if ( OO.ui.isMobile() ) {
					mw.hook( 've.activationComplete' ).add( () => {
						this.showToneEditCheck();
					} );
				} else {
					this.showToneEditCheck();
				}
			},
		);

		if ( OO.ui.isMobile() ) {
			return;
		}

		mw.hook( 've.activationComplete' ).add( () => {
			mw.hook( 'growthExperiments.structuredTask.showOnboardingIfNeeded' ).fire();
		} );
	}

	showToneEditCheck() {
		const branchNodesWithTextFromVE = this.getContentBranchNodesWithTextFromVE();
		// TODO: add tracking for similarity score and second-highest similarity score

		const bestMatch = simpleLevenshtein.findBestMatch(
			this.taskData.toneData.text,
			branchNodesWithTextFromVE.map( ( p ) => p.text ),
		);

		GrowthSuggestionToneCheck.static.setOverride(
			branchNodesWithTextFromVE[ bestMatch.bestMatchIndex ].node,
			ve.init.target.surface.model.documentModel,
		);
		ve.init.target.surface.getModel().emit( 'undoStackChange' );
	}

	// TODO: make this private once Grade A support is raised to at least Safari 15, see T395347
	getContentBranchNodesWithTextFromVE() {
		const nodes = [];
		const surfaceModel = ve.init.target.surface.getModel();
		const doc = surfaceModel.getDocument();
		const contentBranchNodes = doc.getNodesByType( ve.dm.ContentBranchNode, true );
		for ( const contentBranchNode of contentBranchNodes ) {
			const text = contentBranchNode.type === 'paragraph' ? doc.data.getText( true, contentBranchNode.getRange() ) : '';
			nodes.push( {
				node: contentBranchNode,
				text: text,
			} );
		}
		return nodes;
	}
}

module.exports = ReviseToneInitializer;
