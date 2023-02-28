# GrowthExperiments frontend docs

The GrowthExperiments frontend docs site is built with [VitePress](https://vitepress.vuejs.org/), a static site generator
built with [Vite](https://vitejs.dev/) as build tool and Vue 3.

## Getting started

Install [NodeJS](https://nodejs.org/) 14+ and then from the project root (`documentation/frontend`):

```
$ npm install
$ npm run docs:dev
```

This will serve the VitePress GrowthExperiments docs site locally at http://localhost:5173/GrowthExperiments/master/js/frontend/.

## Writing docs

General docs are located in the `docs/` directory. `docs/index.md` is the landing page of the static
site, and other docs are organized into folders.

Docs are written in Markdown, see the [VitePress docs](https://vitepress.vuejs.org/guide/markdown.html)
for details.

### Component demos

Components to demo should use Vue's SFC structure and `.vue`. Create them in the `component-demos` folder, then create a new markdown
file in `docs/demos` directory, eg: `docs/demos/my-component-demo.md`.

Use `npm run components:lint` to lint the demo components with the same eslint configuration that will be 
used in Wikimedia's CI.

#### Standalone demos

Vite is also used to build the component demos in a stand-alone mode, suitable for embedding them
in a MediaWiki environment (useful for QA-ing GrowthExperiments components in a more production-like setup).

The Vite config used to build standalone demos lives in the workspace root: `vite.components.config.js`.
Running `npm run components:build` in this workspace will output bundled JS and CSS files in `/dist` that
are suitable for importing into another project.

To add a new component to the bundle re-export the component in `component-demos/lib.js`. Components used only 
for demoing purposes should not be added to the lib file (eg: `MyComponentDemo.vue` ), only demoed components 
should be added (eg: `MyComponent.vue` ).

## Configuration

### VitePress config

General site information and sidebar configuration are housed in `docs/.vitepress/config.js`. See
the [VitePress docs](https://vitepress.vuejs.org/config/basics.html) or the
[`Config` type definition](https://github.com/vuejs/vitepress/blob/main/src/client/theme-default/config.ts)
for more info.

### Theme config

None at the moment

### Vite config

VitePress uses Vite for building and serving the live GrowthExperiments docs site. Configuration can be
overridden via `docs/vite.config.js`. For example, you can add
[Vite plugins](https://vitejs.dev/plugins/) here. 

Note than in the current setup the file does not exist
and all VitePress defaults for vite are used. `vite.components.config.js` is NOT the docs site
vite configuration and serves other purposes. See [Standalone demos](#Standalone_demos)