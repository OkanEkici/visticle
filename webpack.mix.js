const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.autoload({
    jquery: ['$', 'window.jQuery', 'jQuery']
}).js('resources/js/app.js', 'public/js')
    .sass('resources/sass/app.scss', 'public/css')
    .sass('resources/sass/app-dark.scss', 'public/css');


mix.copyDirectory('resources/theme/src/assets/javascript/pages', 'public/assets/javascript/pages');
mix.copyDirectory('resources/theme/src/assets/images', 'public/assets/img');
mix.copyDirectory('resources/theme/src/assets/vendor', 'public/assets/vendor');
mix.copy('resources/theme/src/assets/javascript/theme.js', 'public/assets/javascript/theme.js');
