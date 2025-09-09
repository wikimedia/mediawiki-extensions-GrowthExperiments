const suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();
const simpleLevenshtein = require( '../../utils/SimpleLevenshtein.js' );

class ReviseToneInitializer {

	// TODO: use private class fields once Grade A support is raised to at least Safari 15, see T395347
	// #taskData;

	constructor() {
		this.taskData = suggestedEditSession.taskData;
	}

	initialize() {
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

		// FIXME: figure out when to know that VE is ready so that we can show trigger this
		mw.hook( 've.activationComplete' ).add( () => {
			mw.hook( 'growthExperiments.structuredTask.showOnboardingIfNeeded' ).fire();
		} );
	}

	showToneEditCheck() {
		const paragraphsWithTextFromVE = this.getParagraphsWithTextFromVE();
		const bestMatch = simpleLevenshtein.findBestMatch(
			this.taskData.toneData.text,
			paragraphsWithTextFromVE.map( ( p ) => p.text ),
		);
		// TODO: add tracking for similarity score and second-highest similarity score

		// TODO: trigger Tone EditCheck once T400335 is done
		if ( OO.ui.isMobile() ) {
			mw.notify(
				`Show Tone EditCheck for the paragraph at position ${ bestMatch.bestMatchIndex + 1 }.`,
				{
					title: 'Revise Tone Suggested Edit',
					autoHide: false,
				},
			);
		} else {
			// eslint-disable-next-line no-alert
			alert( `Show Tone EditCheck for the paragraph at position ${ bestMatch.bestMatchIndex + 1 }.` );
		}
	}

	// TODO: make this private once Grade A support is raised to at least Safari 15, see T395347
	getParagraphsWithTextFromVE() {
		const paragraphs = [];
		const surfaceModel = ve.init.target.surface.getModel();
		const store = surfaceModel.getDocument().getStore();
		const paragraphNodes = surfaceModel.getDocument().getNodesByType( 'paragraph' );
		for ( const paragraphNode of paragraphNodes ) {
			const hash = paragraphNode.element.originalDomElementsHash;
			if ( store.hashStore[ hash ] === undefined ) {
				continue;
			}
			const paragraphText = store.hashStore[ hash ][ 0 ].textContent.trim();
			if ( paragraphText ) {
				paragraphs.push( {
					node: paragraphNode,
					text: paragraphText,
				} );
			}
		}
		return paragraphs;
	}
}

module.exports = ReviseToneInitializer;
