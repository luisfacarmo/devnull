import { createApp } from 'vue'
import App from './App.vue'

const appElement = document.getElementById('devnull-app')
if (appElement) {
	const app = createApp(App)
	app.config.globalProperties.t = t
	app.config.globalProperties.n = n
	app.mount(appElement)
}
