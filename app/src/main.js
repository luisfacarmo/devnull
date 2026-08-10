import Vue from 'vue'
import App from './App.vue'

Vue.mixin({ methods: { t, n } })

const appElement = document.getElementById('devnull-app')

if (appElement) {
	new Vue({
		el: '#devnull-app',
		render: h => h(App),
	})
}
