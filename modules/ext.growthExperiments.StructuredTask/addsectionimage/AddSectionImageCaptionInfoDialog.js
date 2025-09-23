const AddImageCaptionInfoDialog = require( '../addimage/AddImageCaptionInfoDialog.js' ),
	suggestedEditSession = require( 'ext.growthExperiments.SuggestedEditSession' ).getInstance();

/** @inheritDoc */
function AddSectionImageCaptionInfoDialog() {
	AddSectionImageCaptionInfoDialog.super.apply( this, arguments );
	this.CAPTION_ONBOARDING_PREF = 'growthexperiments-addsectionimage-caption-onboarding';
}
OO.inheritClass( AddSectionImageCaptionInfoDialog, AddImageCaptionInfoDialog );

AddSectionImageCaptionInfoDialog.static.name = 'addSectionImageCaptionInfo';

AddSectionImageCaptionInfoDialog.static.title = mw.message(
	'growthexperiments-addsectionimage-caption-info-dialog-title',
).text();

AddSectionImageCaptionInfoDialog.static.message = function () {
	const articleTitle = suggestedEditSession.getCurrentTitle().getNameText(),
		/** @type {mw.libs.ge.AddSectionImageArticleTarget} **/
		articleTarget = ve.init.target,
		contentLanguageName = articleTarget.getSelectedSuggestion().metadata.contentLanguageName,
		$guidelines = $( '<ul>' ).addClass( 'mw-ge-addImageCaptionInfoDialog-list' ),
		guidelineItems = [
			mw.message(
				'growthexperiments-addsectionimage-caption-info-dialog-guidelines-review',
			).parse(),
			mw.message(
				'growthexperiments-addsectionimage-caption-info-dialog-guidelines-describe',
			).params( [
				articleTitle,
				articleTarget.getSelectedSuggestion().visibleSectionTitle,
			] ).parse(),
			mw.message(
				'growthexperiments-addsectionimage-caption-info-dialog-guidelines-neutral',
			).parse(),
		];
	let languageGuideline;
	if ( contentLanguageName ) {
		languageGuideline = mw.message(
			'growthexperiments-addsectionimage-caption-info-dialog-guidelines-language',
		).params( [ contentLanguageName ] ).parse();
	} else {
		languageGuideline = mw.message(
			'growthexperiments-addsectionimage-caption-info-dialog-guidelines-language-generic',
		).parse();
	}
	guidelineItems.push( languageGuideline );
	guidelineItems.forEach( ( guidelineItemText ) => {
		$guidelines.append( $( '<li>' ).html( guidelineItemText ) );
	} );
	return $( '<div>' ).append( [
		mw.message( 'growthexperiments-addsectionimage-caption-info-dialog-message' ).params(
			[ articleTitle ],
		).parse(),
		$guidelines,
	] );
};

module.exports = AddSectionImageCaptionInfoDialog;
