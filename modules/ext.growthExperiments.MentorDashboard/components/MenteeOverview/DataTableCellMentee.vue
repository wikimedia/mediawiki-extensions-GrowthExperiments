<template>
	<div class="ext-growthExperiments-DataTableCellMentee">
		<cdx-button
			weight="quiet"
			:aria-label="ariaLabel"
			@click="toggleStarred"
		>
			<cdx-icon
				class="ext-growthExperiments-DataTableCellMentee__star-icon"
				:icon="value.isStarred ? cdxIconUnStar : cdxIconStar"
			></cdx-icon>
		</cdx-button>
		<div class="ext-growthExperiments-DataTableCellMentee__user-info">
			<div
				class="ext-growthExperiments-DataTableCellMentee-UserLink"
				:class="{ 'ext-growthExperiments-DataTableCellMentee__suppressed': value.userIsHidden }"
			>
				<a
					class="ext-growthExperiments-DataTableCellMentee-UserLink__username"
					:class="{ new: !value.userPageExists }"
					:href="usernameHref"
				>
					{{ value.username }}
				</a>
				<span class="ext-growthExperiments-DataTableCellMentee-UserLink__talkpage">
					<a
						:class="{ new: !value.userTalkExists }"
						:href="usertalkHref"
					>
						{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-talk' ) }}
					</a>
				</span>
			</div>
			<div class="ext-growthExperiments-DataTableCellMentee__user-info__last-seen">
				{{ $i18n( 'growthexperiments-mentor-dashboard-mentee-overview-active-ago', value.lastActive ) }}
			</div>
		</div>
	</div>
</template>

<script>
const { CdxIcon, CdxButton } = require( '@wikimedia/codex' );
const { cdxIconStar, cdxIconUnStar } = require( '../../../vue-components/icons.json' );

// @vue/component
module.exports = exports = {
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
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
		},
		usertalkHref() {
			return ( new mw.Title( this.value.username, 3 ) ).getUrl();
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
@import 'mediawiki.skin.variables.less';
@import '../../../utils/mixins.less';

@mentee-table-last-seen-font-size: 12px;

.ext-growthExperiments-DataTableCellMentee {
	display: flex;

	&__star-icon {
		opacity: @opacity-icon-subtle;

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

	&-UserLink {
		display: flex;

		&__username {
			.text-ellipsis();
			min-width: 0;
		}

		&__talkpage {
			margin-left: 2px;
		}

		&__talkpage::before {
			content: '(';
		}

		&__talkpage::after {
			content: ')';
		}
	}

	&__suppressed {
		text-decoration: line-through;
		text-decoration-style: double;
	}
}
</style>
