<template>
	<wvui-toggle-button
		action="progressive"
		class="mw-ge-help-panel-button"
		:is-active="isActive"
		:class="{ 'mw-ge-help-panel-button--open': isActive }"
		@click="toggle"
	>
		<wvui-icon
			v-if="!isActive"
			:icon="isActive ? iconChevDown : iconHelp"
			:icon-title="text"
			:class="{
				'chevron-down': isActive,
				'icon-help': !isActive
			}"
		></wvui-icon>
		<wvui-icon
			v-else
			:icon="iconChevDown"
			:icon-title="text"
			class="chevron-down"
		></wvui-icon>
	</wvui-toggle-button>
</template>

<script>
var WvuiButton = require( 'wvui' ).WvuiButton;
var WvuiIcon = require( 'wvui' ).WvuiIcon;
var IconHelp = require( '../icons.js' ).IconHelp;
var IconChevronDown = require( '../icons.js' ).IconChevronDown;

// @vue/component
module.exports = {
	components: {
		'wvui-toggle-button': WvuiButton,
		'wvui-icon': WvuiIcon
	},
	props: {
		text: { type: String, default: 'Some title' }
	},
	data: function () {
		return {
			isActive: false,
			iconChevDown: IconChevronDown.path,
			// TODO test ltr-rtl
			iconHelp: IconHelp.path
		};
	},
	methods: {
		toggle: function () {
			this.isActive = !this.isActive;
		}
	}
};
</script>
