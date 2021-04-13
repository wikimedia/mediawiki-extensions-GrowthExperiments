module.exports = ( function () {

	/**
	 * Check whether the specified toolbar already has title element
	 *
	 * @param {jQuery} $toolbarElement
	 * @return {boolean}
	 */
	function toolbarHasTitleElement( $toolbarElement ) {
		return $toolbarElement.has( '.mw-ge-machine-suggestions-mode-title' ).length === 0;
	}

	/**
	 * Return machine suggestions mode title content
	 *
	 * @param {Object} [config]
	 * @param {boolean} [config.includeIcon] Whether icon should be shown next to the title
	 * @return {jQuery}
	 */
	function getTitleElement( config ) {
		config = config || {};
		return $( '<div>' ).addClass( 'mw-ge-machine-suggestions-mode-title' ).append( [
			config.includeIcon ? new OO.ui.IconWidget( { icon: 'robot' } ).$element : '',
			$( '<span>' ).addClass( 'mw-ge-machine-suggestions-mode-title-text' ).text(
				mw.message( 'growthexperiments-addlink-ve-machine-suggestions-mode-title' ).text()
			)
		] );
	}

	/**
	 * Return an array of tools for machine suggestions mode on mobile
	 *
	 * @param {Object[]} currentTools Existing VE tools
	 * @return {Object[]}
	 */
	function getMobileTools( currentTools ) {
		var activeTools = currentTools.filter( function ( tool ) {
			return [ 'editMode', 'save', 'back' ].indexOf( tool.name ) !== -1;
		} );
		activeTools.push(
			{
				name: 'machineSuggestionsPlaceholder',
				include: [ 'machineSuggestionsPlaceholder' ]
			}
		);
		return activeTools;
	}

	/**
	 * Return an array of action groups for machine suggestions mode
	 *
	 * @param {Object[]} currentGroups Existing VE action groups
	 * @return {Object[]}
	 */
	function getActionGroups( currentGroups ) {
		var activeGroups = currentGroups.filter( function ( tool ) {
			return [ 'editMode', 'back' ].indexOf( tool.name ) !== -1;
		} );
		activeGroups.push(
			{
				name: 'save',
				type: 'bar',
				include: [ 'machineSuggestionsSave' ]
			}
		);
		return activeGroups;
	}

	return {
		toolbarHasTitleElement: toolbarHasTitleElement,
		getTitleElement: getTitleElement,
		getMobileTools: getMobileTools,
		getActionGroups: getActionGroups
	};

}() );
