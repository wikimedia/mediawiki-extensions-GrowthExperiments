/**
 * Special:Home, the Personal Dashboard render of the GrowthExperiments `home`
 * module group.
 *
 * PersonalDashboard owns the frame. The server renders one card per module, and
 * the dashboard app teleports a Vue island into the body of each card. The
 * selectors below come from both halves.
 */
class SpecialHome {
	// The greeting, which holds the user name.
	public get title(): ReturnType<typeof cy.get> {
		return cy.get( '#firstHeading' );
	}

	// The server-rendered card of one module, e.g. `ext.growthExperiments.impact`.
	// The id of the card is the module name, which holds dots, so read the data
	// attribute instead of the id.
	public module( name: string ): ReturnType<typeof cy.get> {
		return cy.get( `.personal-dashboard-module[data-module-name="${ name }"]` );
	}

	// The stand-in that shows one module as the whole view: an in-page frame on a
	// wide viewport, a dialog on a narrow one. Match both, so an assertion does
	// not depend on the viewport of the test.
	public get focusedView(): ReturnType<typeof cy.get> {
		return cy.get( '.personal-dashboard-focused-frame, .personal-dashboard-dialog' );
	}

	public get suggestedEditsCards(): ReturnType<typeof cy.get> {
		return cy.get( '.growthexperiments-suggested-edits__card' );
	}

	// The show more button, found by its accessible name: it adds suggestions to the
	// card, or opens the full list on a narrow viewport.
	public get suggestedEditsShowMoreButton(): ReturnType<typeof cy.get> {
		return cy.get( 'button[aria-label="Show more suggested edits"]' );
	}

	public get interestsNotice(): ReturnType<typeof cy.get> {
		return cy.get( '.growthexperiments-interests-notice' );
	}

	public get interestsNoticeDismissButton(): ReturnType<typeof cy.get> {
		return cy.get( '.growthexperiments-interests-notice .cdx-message__dismiss-button' );
	}

	public get welcomeBackDialog(): ReturnType<typeof cy.get> {
		return cy.get( '.growthexperiments-welcome-back-dialog' );
	}

	public get welcomeBackDialogCloseButton(): ReturnType<typeof cy.get> {
		return cy.get( '.growthexperiments-welcome-back-dialog .cdx-dialog__header__close-button' );
	}

	public get impactTotalEdits(): ReturnType<typeof cy.get> {
		return cy.get( '[data-link-id="impact-total-edits"]' );
	}
}

export default SpecialHome;
