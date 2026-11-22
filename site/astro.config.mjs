import { defineConfig } from 'astro/config';

export default defineConfig({
	site: 'https://adotevica.com.br',
	trailingSlash: 'ignore',
	build: {
		// Um único CSS por página, sem arquivos minúsculos separados.
		inlineStylesheets: 'never',
	},
	server: { port: 4321 },
	devToolbar: { enabled: false },
});
