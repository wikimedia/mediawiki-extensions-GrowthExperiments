export default {
	lang: 'en-US',
	title: 'GrowthExperiments Frontend Documentation',
	description: 'Documentation and prototypes for GrowthExperiments',
	// Use the same base URL as it will be used in "doc.wikimedia.org" for simplicity. The site
	// will be served in the following URLs on each environment
	// - development (npm run docs:dev) http://localhost:4173/GrowthExperiments/master/js/frontend/
	// - preview (npm run docs:preview) http://localhost:5173/GrowthExperiments/master/js/frontend/
	// - production (npm run doc from root repostory) https://doc.wikimedia.org/GrowthExperiments/master/js/frontend/
	base: '/GrowthExperiments/master/js/frontend',
	themeConfig: {
		logo: {
			src: '/logo-Wikimedia.svg', alt: 'Wikimedia'
		}
	},
	// The build output location for the docs site, relative to the VitePress project
	// root (/documentation/frontend/docs folder if you're running vitepress build docs).
	outDir: '../../../docs/frontend'
};
