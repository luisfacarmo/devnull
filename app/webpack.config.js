const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Override entry to match the script name loaded in PageController
webpackConfig.entry = {
	'devnull-main': path.join(__dirname, 'src', 'main.js'),
}

module.exports = webpackConfig
