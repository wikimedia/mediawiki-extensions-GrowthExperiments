<template>
	<div class="data-table-cell-mentee">
		<cdx-button
			type="quiet"
			@click="toggleStarred"
		>
			<cdx-icon
				class="star-icon"
				:icon="value.isStarred ? cdxIconUnStar : cdxIconStar"
				:icon-label="value.isStarred ?
					$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-unstar-mentee-icon-label' ) :
					$i18n( 'growthexperiments-mentor-dashboard-mentee-overview-star-mentee-icon-label' )
				"
			></cdx-icon>
		</cdx-button>
		<div class="user-info">
			<a
				class="username"
				:class="{ new: !value.userPageExists }"
				:href="usernameHref"
			>
				{{ value.username }}
			</a>
			<div class="last-seen">
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
@import '../variables.less';
@import '../../../utils/mixins.less';

@mentee-table-last-seen-font-size: 12px;

.data-table-cell-mentee {
	display: flex;

	.star-icon {
		opacity: 0.66;

		> svg {
			cursor: pointer;
		}
	}

	.user-info {
		flex: 2;
		padding-right: 8px;
		.text-ellipsis();

		.last-seen {
			font-size: @mentee-table-last-seen-font-size;
			color: @colorGray500;
		}
	}
}
</style>
