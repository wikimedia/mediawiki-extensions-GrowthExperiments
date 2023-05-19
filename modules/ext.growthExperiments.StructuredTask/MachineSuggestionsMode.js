module.exports = ( function () {
	var SuggestionInteractionLogger = require( './SuggestionInteractionLogger.js' ),
		// The order of the tools here correspond to the order they are shown in editMode dropdown.
		editModeToolNames = [ 'editModeMachineSuggestions', 'editModeVisualWithSuggestions' ];

	/**
	 * Check whether the specified toolbar already has title element
	 *
	 * @param {jQuery} $toolbarElement
	 * @return {boolean}
	 */
	function canAddToolbarTitle( $toolbarElement ) {
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
				mw.message( 'growthexperiments-structuredtask-ve-machine-suggestions-mode-title' ).text()
			)
		] );
	}

	/**
	 * Return the toolgroup for the edit mode dropdown with visual and machine suggestions modes.
	 * This replaces VE's 'editMode' toolgroup.
	 *
	 * @return {Object[]}
	 */
	function getEditModeToolGroup() {
		return {
			name: 'suggestionsEditMode',
			type: 'list',
			icon: 'edit',
			title: mw.message( 'growthexperiments-structuredtask-editmode-selection-label' ).text(),
			label: mw.message( 'growthexperiments-structuredtask-editmode-selection-label' ).text(),
			invisibleLabel: true,
			include: editModeToolNames
		};
	}

	/**
	 * Update edit mode tools in editMode group and return an array of tools
	 *
	 * @param {Object[]} currentTools Existing VE tools
	 * @return {Object[]}
	 */
	function updateEditModeTool( currentTools ) {
		return currentTools.map( function ( tool ) {
			if ( tool.name === 'editMode' ) {
				return getEditModeToolGroup();
			}
			return tool;
		} );
	}

	/**
	 * Return an array of tools for machine suggestions mode on mobile
	 *
	 * @param {Object[]} currentTools Existing VE tools
	 * @return {Object[]}
	 */
	function getMobileTools( currentTools ) {
		var activeTools = currentTools.filter( function ( tool ) {
			return [ 'save', 'back' ].indexOf( tool.name ) !== -1;
		} );
		activeTools.push(
			{
				name: 'machineSuggestionsPlaceholder',
				include: [ 'machineSuggestionsPlaceholder' ]
			},
			getEditModeToolGroup()
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
		return updateEditModeTool( activeGroups );
	}

	/**
	 * Add click event listener on editMode toolbar group
	 *
	 * @param {jQuery} $toolbar Toolbar element
	 */
	function trackEditModeClick( $toolbar ) {
		var $editModeToolbarGroup = $toolbar.find( '.ve-ui-toolbar-group-suggestionsEditMode' );
		if ( $editModeToolbarGroup.length ) {
			$editModeToolbarGroup.on( 'click', function () {
				SuggestionInteractionLogger.log(
					'editmode_click',
					'',
					// eslint-disable-next-line camelcase
					{ active_interface: 'machinesuggestions_mode' }
				);
			} );
		}
	}

	/**
	 * Disabling virtual keyboard and text selection on the editing surface (HACK)
	 *
	 * @param {ve.ui.Surface} surface
	 */
	function disableVirtualKeyboard( surface ) {
		surface.getView().$documentNode
			.attr( 'contenteditable', false )
			.addClass( 'mw-ge-user-select-none' );
	}

	/**
	 * Undo hack for disabling virtual keyboard and text selection on the editing surface
	 *
	 * @param {ve.ui.Surface} surface
	 * @param {boolean} [disableDocumentEdit] Whether the keyboard should be enabled but the
	 * document should not be editable. This is used when only certain parts of the document should
	 * be editable (ex: caption during add image task)
	 */
	function enableVirtualKeyboard( surface, disableDocumentEdit ) {
		surface.getView().$documentNode
			.attr( 'contenteditable', !disableDocumentEdit )
			.removeClass( 'mw-ge-user-select-none' );
	}

	/**
	 * Add a hook to allow save action to be triggered from outside the ArticleTarget
	 *
	 * @param {ve.ui.Surface} surface
	 */
	function addSaveHook( surface ) {
		mw.hook( 'growthExperiments.contextItem.saveArticle' ).add( function () {
			surface.executeCommand( 'showSave' );
		} );
	}

	/**
	 * Prevent pasting into the article surface
	 * This is used when the surface is not read-only but it still should not be editable
	 * (for example, during caption step of add image).
	 *
	 * @param {ve.ui.Surface} surface
	 */
	function disableSurfacePaste( surface ) {
		surface.getView().$attachedRootNode.off( 'paste' );
	}

	/**
	 * Enable pasting into the article surface
	 *
	 * @param {ve.ui.Surface} surface
	 */
	function enableSurfacePaste( surface ) {
		var surfaceView = surface.getView();
		surfaceView.$attachedRootNode.on( 'paste', surfaceView.onPaste.bind( surfaceView ) );
	}

	return {
		canAddToolbarTitle: canAddToolbarTitle,
		getTitleElement: getTitleElement,
		getMobileTools: getMobileTools,
		getActionGroups: getActionGroups,
		getEditModeToolNames: function () {
			return editModeToolNames;
		},
		updateEditModeTool: updateEditModeTool,
		getEditModeToolGroup: getEditModeToolGroup,
		trackEditModeClick: trackEditModeClick,
		disableVirtualKeyboard: disableVirtualKeyboard,
		enableVirtualKeyboard: enableVirtualKeyboard,
		addSaveHook: addSaveHook,
		disableSurfacePaste: disableSurfacePaste,
		enableSurfacePaste: enableSurfacePaste
	};

}() );
