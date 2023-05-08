import DefaultTheme from 'vitepress/theme'
import { createPinia } from 'pinia';

export default {
  Layout: DefaultTheme.Layout,
  enhanceApp(ctx) {
    ctx.app.use( createPinia() )
  }
}