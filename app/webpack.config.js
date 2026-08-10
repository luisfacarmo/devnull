const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Override entry — Nextcloud loads scripts as {appid}-{name}
// So we name it just 'main' and NC will load it as 'devnull-main'
webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.js'),
}

module.exports = webpackConfig
