let mix = require('laravel-mix');

let fs = require('fs');
let path = require('path');

const resourcesPath = fs.realpathSync(path.join(__dirname, 'resources'));

const jsFiles = fs.readdirSync(path.join(resourcesPath, 'js'));
const scssFiles = fs.readdirSync(path.join(resourcesPath, 'sass')).filter(str => /^(?!_)[^\\/]+$/.test(str));

mix.disableNotifications();

// const basename = (filename) => filename.replace(/\..+$/, '');

jsFiles.forEach(filename => {
  if (!filename.includes('.')) return;
  mix.ts(`resources/js/${filename}`, `js`);
});

scssFiles.forEach(filename => {
  if (!filename.includes('.')) return;
  mix.sass(`resources/sass/${filename}`, 'css');
});

// pdf.js worker for the bills page, loaded from /js/pdf.worker.min.js
mix.copy('node_modules/pdfjs-dist/legacy/build/pdf.worker.min.js', 'public/js/pdf.worker.min.js');

mix.autoload({ 'jquery': ['window.$', 'window.jQuery'] });

mix.preact();

mix.version();

mix.webpackConfig({
  resolve: {
    fallback: {
      'react-native-fs': false,
      fs: false,
    },
  },
});
