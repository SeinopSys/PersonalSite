/* eslint-env node */
/* eslint no-console:0 */
(function () {

    "use strict";

    const
        Fiber = require('fibers'),
        chalk = require('chalk'),
        gulp = require('gulp'),
        plumber = require('gulp-plumber'),
        sass = require('gulp-sass')(require('sass')),
        autoprefixer = require('gulp-autoprefixer'),
        cleanCss = require('gulp-clean-css'),
        terser = require('gulp-terser'),
        babel = require('gulp-babel'),
        cached = require('gulp-cached'),
        sourceMaps = require('gulp-sourcemaps'),
        workingDir = __dirname;

    class Logger {
        constructor(prompt) {
            this.prefix = `[${chalk.blue(prompt)}] `;
        }

        log(message) {
            console.log(this.prefix + message);
        }

        error(message) {
            if (typeof message === 'string') {
                message = message.trim()
                    .replace(/[/\\]?public/, '');
                console.error(this.prefix + 'Error in ' + message);
            } else console.log(JSON.stringify(message, null, '4'));
        }
    }

    let SASSL = new Logger('scss'),
        SASSWatchArray = ['resources/sass/*.scss'];
    gulp.task('scss', () => {
        return gulp.src(SASSWatchArray)
            .pipe(plumber(function (err) {
                SASSL.error(err.relativePath + '\n' + ' line ' + err.line + ': ' + err.messageOriginal);
                this.emit('end');
            }))
            .pipe(sourceMaps.init())
            .pipe(sass({
                fiber: Fiber,
                outputStyle: 'expanded',
                errLogToConsole: true,
            }))
            .pipe(autoprefixer({
                browsers: ['last 2 versions', 'not ie <= 11'],
            }))
            .pipe(cleanCss({
                processImport: false,
                compatibility: '-units.pc,-units.pt'
            }))
            .pipe(sourceMaps.write('../maps'))
            .pipe(gulp.dest('public/css'));
    });

    let JSL = new Logger('js'),
        JSWatchArray = ['resources/js/*.js', 'resources/js/**/*.js'];
    gulp.task('js', () => {
        return gulp.src(JSWatchArray)
            .pipe(sourceMaps.init())
            .pipe(cached('js', {optimizeMemory: true}))
            .pipe(plumber(function (err) {
                err =
                    err.fileName
                        ? err.fileName.replace(workingDir, '') + '\n  line ' + (
                        err._babel === true
                            ? err.loc.line
                            : (err.lineNumber || '?')
                    ) + ': ' + err.message.replace(/^[/\\]/, '')
                        .replace(err.fileName.replace(/\\/g, '/') + ': ', '')
                        .replace(/\(\d+(:\d+)?\)$/, '')
                        : err;
                JSL.error(err);
                this.emit('end');
            }))
            .pipe(babel(require('./.babelrc.js')))
            .pipe(terser({
                compress: {
                    drop_debugger: false
                }
            }))
            .pipe(sourceMaps.write('../maps'))
            .pipe(gulp.dest('public/js'));
    });

    const createWatchers = done => {
        gulp.watch(JSWatchArray, {debounceDelay: 2000}, gulp.series('js'));
        JSL.log('File watcher active');
        gulp.watch(SASSWatchArray, {debounceDelay: 2000}, gulp.series('scss'));
        SASSL.log('File watcher active');
        done();
    };

    gulp.task('default', gulp.series('js', 'scss'));

    gulp.task('watch', gulp.series('default', createWatchers));

})();
