<template>
	<div class="ext-growthExperiments-DataTableCellMentee">
		<cdx-button
			type="quiet"
			@click="toggleStarred"
		>
			<cdx-icon
				class="ext-growthExperiments-DataTableCellMentee__star-icon"
				:icon="value.isStarred ? cdxIconUnStar : cdxIconStar"
				:icon-label="value.isStarred ?
					$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-unstar-mentee-icon-label' ) :
					$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-star-mentee-icon-label' )
				"
			></cdx-icon>
		</cdx-button>
		<div class="ext-growthExperiments-DataTableCellMentee__user-info">
			<a
				:class="{ new: !value.userPageExists, 'ext-growthExperiments-DataTableCellMentee__suppressed': value.userIsHidden }"
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
		usernameHref: function () {
			return ( new mw.Title( this.value.username, 2 ) ).getUrl();
		}
	},
	methods: {
		toggleStarred: function () {
			this.$emit( 'toggle-starred', { starred: this.value.isStarred, userId: this.value.userId } );
		}
	}
};
</script>

<style lang="less">
@import '../../../vue-components/variables.less';
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
			color: @colorGray500;
		}
	}

	&__suppressed &__username {
		text-decoration: line-through;
		text-decoration-style: double;
	}
}
</style>
