const pjson = require('./package.json');
const glob = require('glob');
const path = require('path');
const version = pjson.version;
const fs = require("fs");

let mix = require('laravel-mix');
require('laravel-mix-eslint');
require('mix-tailwindcss');
require('dotenv').config({ path: 'config/.env' });

// We set the public path to public/dist
// All output paths in mix methods will be relative to this path
mix
    .setPublicPath('public/dist')
    .setResourceRoot('../');

// JS Compilation
mix
    .combine('./public/assets/js/libs/prism/prism.js', `js/compiled-footer.${version}.min.js`)
    .js('./public/assets/js/app/htmx.js', `js/compiled-htmx.${version}.min.js`)
    .js('./public/assets/js/app/htmx-extensions.js', `js/compiled-htmx-extensions.${version}.min.js`)
    .js("./node_modules/@lottiefiles/lottie-player/dist/lottie-player.js", `js/compiled-lottieplayer.${version}.min.js`)
    .combine([
        "./public/assets/js/app/app.js",
        "./public/assets/js/app/core/snippets.js",
        "./public/assets/js/app/core/modals.js",
        "./public/assets/js/app/core/tableHandling.js",
        "./public/assets/js/app/core/datePickers.js",
        "./public/assets/js/app/core/dateHelper.js",
        "./public/assets/js/app/core/accessibility.js",
        ...glob.sync("./app/Domain/**/*.js").map(f => `./${f}`)
    ], `js/compiled-app.${version}.min.js`)
    .combine([
        "./node_modules/jquery/dist/jquery.js",
        "./public/assets/js/libs/bootstrap.min.js",
    ], `js/compiled-frameworks.${version}.min.js`)
    .combine([
        "./node_modules/jquery-ui-dist/jquery-ui.js",
        "./node_modules/jquery-ui-touch-punch/jquery.ui.touch-punch.js",
        "./node_modules/chosen-js/chosen.jquery.js",
        "./public/assets/js/libs/jquery.growl.js",
        "./public/assets/js/libs/jquery.form.js",
        "./public/assets/js/libs/jquery.tagsinput.min.js",
        "./public/assets/js/libs/bootstrap-fileupload.min.js",
        "./node_modules/jquery-is-in-viewport/dist/isInViewport.jquery.js",
        "./public/assets/js/app/core/nestedSortable.js",
    ], `js/compiled-framework-plugins.${version}.min.js`)
    .combine([
        "./node_modules/luxon/build/global/luxon.js",
        "./node_modules/moment/moment.js",
        "./public/assets/js/libs/jquery.form.js",
        "./node_modules/@popperjs/core/dist/umd/popper.js",
        "./node_modules/tippy.js/dist/tippy-bundle.umd.js",
        "./public/assets/js/libs/slimselect.min.js",
        "./node_modules/canvas-confetti/dist/confetti.browser.js",
        "./public/assets/js/libs/jquery.nyroModal/js/jquery.nyroModal.custom.js",
        "./public/assets/js/libs/uppy/uppy.js",
        "./node_modules/croppie/croppie.js",
        "./node_modules/packery/dist/packery.pkgd.js",
        "./node_modules/imagesloaded/imagesloaded.pkgd.js",
        "./node_modules/shepherd.js/dist/js/shepherd.js",
        "./node_modules/isotope-layout/dist/isotope.pkgd.js",
        "./node_modules/gridstack/dist/gridstack-all.js",
        "./node_modules/jstree/dist/jstree.js",
        "./node_modules/@assuradeurengilde/fontawesome-iconpicker/dist/js/fontawesome-iconpicker.js",
        "./node_modules/leader-line/leader-line.min.js",
        "./public/assets/js/libs/simple-color-picker-master/jquery.simple-color-picker.js",
        "./public/assets/js/libs/emojipicker/vanillaEmojiPicker.js",
        "./node_modules/mermaid/dist/mermaid.min.js",
        "./node_modules/marked/marked.min.js",
    ], `js/compiled-global-component.${version}.min.js`)
    .combine([
        "./node_modules/ical.js/build/ical.min.js",
        "./node_modules/fullcalendar/index.global.min.js",
        "./node_modules/@fullcalendar/icalendar/index.global.min.js",
        "./node_modules/@fullcalendar/google-calendar/index.global.min.js",
        "./node_modules/@fullcalendar/luxon3/index.global.min.js",
    ], `js/compiled-calendar-component.${version}.min.js`)
    .combine([
        "./node_modules/datatables.net/js/jquery.dataTables.js",
        "./node_modules/datatables.net-rowgroup/js/dataTables.rowGroup.js",
        "./node_modules/datatables.net-rowreorder/js/dataTables.rowReorder.js",
        "./node_modules/datatables.net-buttons/js/dataTables.buttons.js",
        "./node_modules/datatables.net-buttons/js/buttons.html5.js",
        "./node_modules/datatables.net-buttons/js/buttons.print.js",
        "./node_modules/datatables.net-buttons/js/buttons.colVis.js",
    ], `js/compiled-table-component.${version}.min.js`)
    .js('./public/assets/js/app/core/tiptap/index.js', `js/compiled-tiptap-editor.${version}.min.js`)
    .combine([
        './public/assets/js/app/core/tiptap/extensions/toolbar.js'
    ], `js/compiled-tiptap-toolbar.${version}.min.js`)
    .combine([
        "./public/assets/js/libs/simpleGantt/snap.svg-min.js",
        "./public/assets/js/libs/simpleGantt/frappe-gantt.js",
    ], `js/compiled-gantt-component.${version}.min.js`)
    .combine([
        "./node_modules/chart.js/dist/chart.js",
        "./node_modules/chartjs-adapter-luxon/dist/chartjs-adapter-luxon.umd.js",
    ], `js/compiled-chart-component.${version}.min.js`);

// CSS Compilation
mix
    .less('./public/assets/less/main.less', `css/main.${version}.min.css`, {
        sourceMap: true,
    })
    .less('./public/assets/less/app.less', `css/app.${version}.min.css`, {
        sourceMap: true,
    })
    .copy('./public/assets/css/components/tiptap-editor.css', `css/tiptap-editor.${version}.min.css`)
    .tailwind();

// Asset Copying
mix
    .copyDirectory('./public/assets/images', 'images')
    .copyDirectory('./public/assets/fonts', 'fonts')
    .copyDirectory('./public/assets/lottie', 'lottie')
    .copy('./node_modules/katex/dist/fonts', 'fonts/katex')
    .copy('./node_modules/katex/dist/katex.min.css', 'css/katex.min.css')
    .copy('./node_modules/katex/dist/fonts', 'css/fonts');

// Webpack Config
mix.webpackConfig({
    devtool: 'inline-source-map',
    resolve: {
        alias: {
            'images': path.resolve(__dirname, 'public/assets/images'),
            'js': path.resolve(__dirname, 'public/assets/js'),
            'css': path.resolve(__dirname, 'public/assets/css'),
            'fonts': path.resolve(__dirname, 'public/assets/fonts')
        }
    }
});
