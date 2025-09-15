import { createApp } from 'vue';
import '@wikimedia/codex/dist/codex.style.css';
import '@wikimedia/codex-design-tokens/theme-wikimedia-ui-root.css';
import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.css';
// import App from '../App.vue';
// @ts-expect-error importing types from .vue file does not work yet?
import CommonComponentsDemo from './demos/CommonComponentsDemo.vue';
import i18nPlugin from './i18nPlugin';
import loggerPlugin from '../../vue-components/plugins/logger';

const devApp = createApp( CommonComponentsDemo, {
	taskType: 'revise-tone',
} );

devApp.use( loggerPlugin, {
	mode: 'dev',
	logger: console,
} );
devApp.use( i18nPlugin );

devApp.mount( '#app' );
