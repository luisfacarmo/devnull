import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { join } from 'path'

// Nextcloud loads scripts as {appid}-{name}.js
// Output must land in js/ so the PHP template can reference it.
export default defineConfig({
	plugins: [vue()],
	build: {
		outDir: 'js',
		emptyOutDir: true,
		rollupOptions: {
			input: join(__dirname, 'src', 'main.js'),
			output: {
				entryFileNames: 'devnull-main.js',
				chunkFileNames: 'devnull-[name].js',
				assetFileNames: 'devnull-[name].[ext]',
			},
		},
		cssCodeSplit: false,
		cssMinify: true,
	},
})
