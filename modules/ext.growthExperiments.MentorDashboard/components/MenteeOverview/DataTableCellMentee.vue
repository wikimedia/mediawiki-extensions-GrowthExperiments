<template>
	<div class="ext-growthExperiments-DataTableCellMentee">
		<cdx-button
			type="quiet"
			:aria-label="ariaLabel"
			@click="toggleStarred"
		>
			<cdx-icon
				class="ext-growthExperiments-DataTableCellMentee__star-icon"
				:icon="value.isStarred ? cdxIconUnStar : cdxIconStar"
			></cdx-icon>
		</cdx-button>
		<div class="ext-growthExperiments-DataTableCellMentee__user-info">
			<a
				:class="{
					new: !value.userPageExists,
					'ext-growthExperiments-DataTableCellMentee__suppressed': value.userIsHidden
				}"
				:href="usernameHref"
			>
				<span class="ext-growthExperiments-DataTableCellMentee__username">
					{{ value.username }}
				</span>
			</a>
			<div class="ext-growthExperiments-DataTableCellMentee__user-info__last-seen">
				{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-active-ago', value.lastActive ) }}
			</div>
		</div>
	</div>
</template>

<script>
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const { cdxIconStar, cdxIconUnStar } = require( '../icons.json' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	components: {
		CdxButton,
		CdxIcon
	},
	props: {
		value: { type: Object, required: true }
	},
	emits: [ 'toggle-starred' ],
	setup() {
		return {
			cdxIconStar,
			cdxIconUnStar
		};
	},
	computed: {
		ariaLabel() {
			return this.value.isStarred ?
				this.$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-unstar-mentee-icon-label' ).text() :
				this.$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-star-mentee-icon-label' ).text();
		},
		usernameHref() {
			return ( new mw.Title( this.value.username, 2 ) ).getUrl();
		}
	},
	methods: {
		toggleStarred() {
			this.$emit( 'toggle-starred', { starred: this.value.isStarred, userId: this.value.userId } );
		}
	}
};
</script>

<style lang="less">
@import ( reference ) '../../../../resources/lib/codex-design-tokens/theme-wikimedia-ui.less';
@import '../../../utils/mixins.less';

@mentee-table-last-seen-font-size: 12px;

.ext-growthExperiments-DataTableCellMentee {
	display: flex;

	&__star-icon {
		opacity: 0.66;

		> svg {
			cursor: pointer;
		}
	}

	&__user-info {
		flex: 2;
		padding-right: 8px;
		.text-ellipsis();

		&__last-seen {
			font-size: @mentee-table-last-seen-font-size;
			color: @color-subtle;
		}
	}

	&__suppressed &__username {
		text-decoration: line-through;
		text-decoration-style: double;
	}
}
</style>
