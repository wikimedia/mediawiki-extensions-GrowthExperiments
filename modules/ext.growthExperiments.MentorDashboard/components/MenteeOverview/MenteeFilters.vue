<template>
	<div class="ext-growthExperiments-MenteeFilters">
		<cdx-button @click="toggleFiltersForm">
			<span>
				{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-add-filter' ) }}
			</span>
			<cdx-icon
				class="ext-growthExperiments-MenteeFilters__expand-icon"
				:icon="showFiltersForm ? cdxIconCollapse : cdxIconExpand"
				:icon-label="iconLabel"
			></cdx-icon>
		</cdx-button>
		<div v-if="showFiltersForm" class="ext-growthExperiments-MenteeFilters__container">
			<mentee-filters-form
				class="ext-growthExperiments-MenteeFilters__form"
				v-bind="data"
				@update:filters="onFiltersUpdate"
				@close="hide"
			></mentee-filters-form>
		</div>
	</div>
</template>

<script>
const { CdxButton, CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconExpand, cdxIconCollapse } = require( '../../../vue-components/icons.json' );
const MenteeFiltersForm = require( './MenteeFiltersForm.vue' );
// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	components: {
		CdxButton,
		CdxIcon,
		MenteeFiltersForm
	},
	props: {
		data: { type: Object, required: true }
	},
	emits: [ 'update:filters' ],
	setup() {
		return {
			cdxIconExpand,
			cdxIconCollapse
		};
	},
	data() {
		return {
			showFiltersForm: false
		};
	},
	computed: {
		iconLabel() {
			return this.showFiltersForm ?
				this.$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-filters-collapse-icon-label' ).text() :
				this.$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-filters-expand-icon-label' ).text();
		}
	},
	methods: {
		onClickOutside( e ) {
			if ( !this.$el.contains( e.target ) ) {
				this.hide();
			}
		},
		hide() {
			this.showFiltersForm = false;
		},
		toggleFiltersForm() {
			this.showFiltersForm = !this.showFiltersForm;
		},
		onFiltersUpdate( $event ) {
			this.$emit( 'update:filters', $event );
			this.hide();
		}
	},
	mounted() {
		window.addEventListener( 'click', this.onClickOutside );
	},
	unmounted() {
		window.removeEventListener( 'click', this.onClickOutside );
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
@import '../../../utils/mixins.less';

.ext-growthExperiments-MenteeFilters {
	&__expand-icon {
		> svg {
			// REVIEW styles copied "by eye", how to affect icon stroke-width,
			// correct API for adding an inline icon inside a CdxButton
			width: 12px;
			height: 16px;
			padding-left: 8px;
		}
	}

	&__container {
		position: relative;
	}

	&__form {
		width: 320px;
		position: absolute;
		z-index: @z-index-above-content;
		top: 2px;
		.popover-base();
	}
}
</style>
