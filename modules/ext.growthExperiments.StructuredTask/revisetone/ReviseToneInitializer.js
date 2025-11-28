const SuggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' );
const suggestedEditSession = SuggestedEditSession.getInstance();
const GrowthSuggestionToneCheck = require( './GrowthSuggestionToneCheck.js' );
const useExperiment = require( './useExperiment.js' );
const simpleLevenshtein = require( '../../utils/SimpleLevenshtein.js' );
const experiment = useExperiment();

class ReviseToneInitializer {

	// TODO: use private class fields once Grade A support is raised to at least Safari 15, see T395347
	// #taskData;
	// #isInitialToneCheck;

	constructor() {
		this.taskData = suggestedEditSession.taskData;
		this.isInitialToneCheck = true;
	}

	initialize() {
		if ( ReviseToneInitializer.hasBeenInitialized ) {
			return;
		}
		ReviseToneInitializer.hasBeenInitialized = true;
		mw.editcheck.editCheckFactory.unregister( mw.editcheck.ToneCheck );
		mw.editcheck.editCheckFactory.register( GrowthSuggestionToneCheck, GrowthSuggestionToneCheck.static.name );
		mw.hook( 'growthExperiments.structuredTask.onboardingCompleted' ).add(
			() => {
				ve.trackSubscribe(
					'activity.editCheck-' + GrowthSuggestionToneCheck.static.name,
					( ...params ) => this.handleEditCheckDialogEvents( ...params ),
				);

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

	handleEditCheckDialogEvents( name, { action } ) {
		if ( this.isInitialToneCheck && action === 'action-edit' ) {
			// The user clicked "Revise" on the initial tone suggestion
			this.isInitialToneCheck = false;
			return;
		}
		const dismissActions = [
			'edit-check-feedback-reason-appropriate',
			'edit-check-feedback-reason-uncertain',
			'edit-check-feedback-reason-other',
		];
		if ( this.isInitialToneCheck && dismissActions.includes( action ) ) {
			// The user submitted the decline-survey on the initial tone suggestion
			if ( action === 'edit-check-feedback-reason-appropriate' ) {
				( new mw.Api() ).postWithToken(
					'csrf',
					{
						action: 'growthinvalidaterevisetonerecommendation',
						title: mw.config.get( 'wgPageName' ),
					},
				);
			}

			mw.track(
				'stats.mediawiki_GrowthExperiments_revise_tone_initial_check_decline_total',
				1,
				{
					reason: action.replace( 'edit-check-feedback-reason-', '' ),
				},
			);
			ve.init.target.tryTeardown().then( this.showCancelledPostEditDialog );
			experiment.send( 'click', {
				/* eslint-disable camelcase */
				action_subtype: 'decline',
				action_source: 'EditCheck-1',
				instrument_name: 'Click on decline revise tone',
				/* eslint-enable camelcase */
			} );
		}
	}

	showCancelledPostEditDialog() {
		suggestedEditSession.setTaskState( SuggestedEditSession.static.STATES.CANCELLED );
		suggestedEditSession.postEditDialogNeedsToBeShown = true;
		suggestedEditSession.save();
		window.setTimeout( () => {
			window.location.reload();
		}, 100 );
	}

	showToneEditCheck() {
		const branchNodesWithTextFromVE = this.getContentBranchNodesWithTextFromVE();
		// TODO: add tracking for similarity score and second-highest similarity score

		const bestMatch = simpleLevenshtein.findBestMatch(
			this.taskData.paragraphText,
			branchNodesWithTextFromVE.map( ( p ) => p.text ),
		);

		GrowthSuggestionToneCheck.static.setOverride(
			branchNodesWithTextFromVE[ bestMatch.bestMatchIndex ].node,
			ve.init.target.surface.model.documentModel,
		);
		ve.init.target.surface.getModel().emit( 'undoStackChange' );
		this.scrollReviseToneIntoView();
		experiment.send( 'page-visited', {
			/* eslint-disable camelcase */
			action_source: 'EditCheck-1',
			instrument_name: 'Article with revise tone recommendation page visited',
			/* eslint-enable camelcase */
		} );
	}

	scrollReviseToneIntoView() {
		let hasBeenScrolledIntoView = false;
		ve.trackSubscribe(
			'activity.editCheck-' + GrowthSuggestionToneCheck.static.name,
			() => {
				if ( hasBeenScrolledIntoView ) {
					return;
				}
				window.setTimeout( () => {
					document.querySelector(
						OO.ui.isMobile() ? '.ve-ui-editCheck-gutter-action' : '.ve-ui-editCheckActionWidget',
					).scrollIntoView( { behavior: 'smooth', block: 'center' } );
				}, 500 );
				hasBeenScrolledIntoView = true;
			},
		);
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
