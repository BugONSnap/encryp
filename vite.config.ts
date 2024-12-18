import { defineConfig } from 'vite';
import { sveltekit } from '@sveltejs/kit/vite';

const API_URL = 'https://formalytics.me/api-cafe';

export default defineConfig({
	plugins: [sveltekit()],
	server: {
		fs: {
			allow: ['..']
		},
		proxy: {
			'/api-cafe': {
				target: API_URL,
				changeOrigin: true,
				rewrite: (path) => path.replace(/^\/api-cafe/, '')
			},
			'/uploads': {
				target: 'https://formalytics.me/uploads',
				changeOrigin: true,
					rewrite: (path) => path.replace(/^\/uploads/, '')
			}
		}
	},
	test: {
		include: ['src/**/*.{test,spec}.{js,ts}']
	},
	define: {
		'import.meta.env.VITE_API_URL': JSON.stringify(API_URL)
	}
});
