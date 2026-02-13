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
	// #isInitialEditSession;

	constructor() {
		this.taskData = suggestedEditSession.taskData;
		this.isInitialToneCheck = true;
		this.isInitialEditSession = true;
	}

	initialize() {
		if ( ReviseToneInitializer.hasBeenInitialized ) {
			return;
		}
		ReviseToneInitializer.hasBeenInitialized = true;
		mw.editcheck.editCheckFactory.unregister( mw.editcheck.ToneCheck );
		mw.editcheck.editCheckFactory.register( GrowthSuggestionToneCheck, GrowthSuggestionToneCheck.static.name );
		ve.trackSubscribe(
			'activity.editCheck-' + GrowthSuggestionToneCheck.static.name,
			( ...params ) => this.handleEditCheckDialogEvents( ...params ),
		);
		if ( OO.ui.isMobile() ) {
			this.initializeMobile();
		} else {
			this.initializeDesktop();
		}
	}

	/**
	 * On desktop this triggers the onboarding after the editor has finished loading
	 *
	 * Also, on desktop, the `mw.hook( 've.newTarget' )` hooks is fired only ever once, and the target is reused.
	 */
	initializeDesktop() {
		mw.hook( 'growthExperiments.structuredTask.onboardingCompleted' ).add(
			() => {
				this.showToneEditCheck();
			},
		);

		mw.hook( 've.newTarget' ).add( ( target ) => {
			if ( target.surface ) {
				mw.hook( 'growthExperiments.structuredTask.showOnboardingIfNeeded' ).fire();
			} else {
				// Using `once` here to avoid firing on subsequent edit sessions
				target.once( 'surfaceReady', () => {
					mw.hook( 'growthExperiments.structuredTask.showOnboardingIfNeeded' ).fire();
				} );
			}
			target.once( 'teardown', this.restoreDefaultToneCheck );
		} );
	}

	/**
	 * On mobile the onboarding is shown before the editor has finished loading
	 * The onboarding is triggered in ext.growthExperiments.HelpPanel/SuggestedEditsGuidance.js
	 * So this only needs to initialize the listener for the editor surface being ready
	 *
	 * On mobile, the `mw.hook( 've.newTarget' )` hooks is fired in every new edit session
	 */
	initializeMobile() {
		mw.hook( 'growthExperiments.structuredTask.onboardingCompleted' ).add(
			() => {
				mw.hook( 've.newTarget' ).add( ( target ) => {
					if ( !this.isInitialEditSession ) {
						return;
					}
					this.isInitialEditSession = false;
					if ( target.surface ) {
						this.showToneEditCheck();
					} else {
						target.once( 'surfaceReady', () => {
							this.showToneEditCheck();
						} );
					}
					target.once( 'teardown', this.restoreDefaultToneCheck );
				} );
			},
		);
	}

	restoreDefaultToneCheck() {
		mw.editcheck.editCheckFactory.unregister( GrowthSuggestionToneCheck );
		mw.editcheck.editCheckFactory.register( mw.editcheck.ToneCheck, mw.editcheck.ToneCheck.static.name );
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
		if (
			this.isInitialToneCheck &&
			dismissActions.includes( action ) &&
			!ve.init.target.surface.model.hasBeenModified()
		) {
			// The user submitted the decline-survey on the initial tone suggestion without any changes
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
					wiki: mw.config.get( 'wgDBname' ),
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

		const start = performance.now();
		const bestMatch = simpleLevenshtein.findBestMatch(
			this.taskData.paragraphText,
			branchNodesWithTextFromVE.map( ( p ) => p.text ),
		);
		mw.track(
			'stats.mediawiki_GrowthExperiments_revise_tone_match_paragraph_seconds',
			performance.now() - start,
			{
				wiki: mw.config.get( 'wgDBname' ),
				platform: OO.ui.isMobile() ? 'mobile' : 'desktop',
			},
		);
		this.trackParagraphMatchQualityMetrics( bestMatch );

		GrowthSuggestionToneCheck.static.setOverride(
			branchNodesWithTextFromVE[ bestMatch.bestMatchIndex ].node,
			ve.init.target.surface.model.documentModel,
		);

		// See T412549 for finding a better way that bind less tightly to VE internals
		ve.init.target.editcheckController.refresh().then( ( actions ) => {
			const action = actions.find( ( a ) => a.getName() === GrowthSuggestionToneCheck.static.name );
			if ( action ) {
				// This select is working around VisualEditor on mobile taking
				// a little while to settle the location of the viewport, so
				// whether we wind up scrolled to the correct point would
				// depend on the exact timing of the initialization here.
				action.select( ve.init.target.surface, false );
				// Focus the tone action and scroll it into view.
				ve.init.target.editcheckController.ensureActionIsShown( action );
			}
		} );
		experiment.send( 'page-visited', {
			/* eslint-disable camelcase */
			action_source: 'EditCheck-1',
			instrument_name: 'Article with revise tone recommendation page visited',
			/* eslint-enable camelcase */
		} );
	}

	/**
	 * @param {{ bestMatchIndex: number, bestMatch: string, ratings: { target: string, distance: number }[] }} bestMatch
	 */
	trackParagraphMatchQualityMetrics( bestMatch ) {
		const bestMatchDistance = bestMatch.ratings[ bestMatch.bestMatchIndex ].distance;

		const normalizedDistance = bestMatchDistance / bestMatch.bestMatch.length;
		mw.track(
			'stats.mediawiki_GrowthExperiments_revise_tone_match_paragraph_normalized_distance_distribution',
			normalizedDistance,
			{
				wiki: mw.config.get( 'wgDBname' ),
				buckets: [ 0, 0.05, 0.1, 0.2, 0.3, 0.5, 0.8, 1.1, 1.5, 2 ],
			},
		);

		const secondBestMatch = bestMatch.ratings.reduce( ( prev, rating, index ) => {
			if ( index !== bestMatch.bestMatchIndex ) {
				if ( prev === null || rating.distance < prev.distance ) {
					return rating;
				}
			}
			return prev;
		}, null );
		if ( !secondBestMatch ) {
			return;
		}
		const ambiguityScore = secondBestMatch.distance === 0 ? 1 : bestMatchDistance / secondBestMatch.distance;
		mw.track(
			'stats.mediawiki_GrowthExperiments_revise_tone_match_paragraph_ambiguity_score_distribution',
			ambiguityScore,
			{
				wiki: mw.config.get( 'wgDBname' ),
				buckets: [ 0, 0.3, 0.5, 0.7, 0.85, 0.95, 1 ],
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
