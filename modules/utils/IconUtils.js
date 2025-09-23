( function () {

	/**
	 * Return icon element to be used with task type
	 *
	 * @param {Object} iconData Icon data for the task type
	 * @param {Object} [config] Optional config for IconWidget
	 * @return {jQuery|string}
	 */
	function getIconElementForTaskType( iconData, config ) {
		if ( !iconData || !( 'icon' in iconData ) ) {
			return '';
		}
		config = config || { invisibleLabel: true };
		// The following messages are used here:
		// * growthexperiments-homepage-suggestededits-tasktype-machine-description
		// * FORMAT growthexperiments-homepage-suggestededits-tasktype-{other}-description
		const label = 'descriptionMessageKey' in iconData ? mw.message( iconData.descriptionMessageKey ).text() : '';
		const iconWidget = new OO.ui.IconWidget( Object.assign( config, {
			icon: iconData.icon,
			classes: [ 'suggested-edits-task-explanation-icon' ],
		} ) );
		if ( label ) {
			iconWidget.setLabel( label );
			iconWidget.setInvisibleLabel( config.invisibleLabel );
		}
		return iconWidget.$element;
	}

	module.exports = {
		getIconElementForTaskType: getIconElementForTaskType,
	};
}() );
