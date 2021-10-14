/**
 *
 * Gulpfile
 *
 * @author Takuto Yanagida
 * @version 2021-10-14
 *
 */

'use strict';

const gulp = require('gulp');

const { makeJsTask }     = require('./task-js');
const { makeSassTask }   = require('./task-sass');
const { makeCopyTask }   = require('./task-copy');
const { makeLocaleTask } = require('./task-locale');


// -----------------------------------------------------------------------------


const js_raw  = makeJsTask(['src/**/*.js', '!src/**/*.min.js'], './dist', 'src');
const js_copy = makeCopyTask('src/**/*.min.js', './dist');
const js      = gulp.parallel(js_raw, js_copy);

const css_copy = makeCopyTask('src/**/*.min.css', './dist');
const css      = gulp.parallel(css_copy);

const sass   = makeSassTask('src/**/*.scss', './dist');
const php    = makeCopyTask('src/**/*.php', './dist');
const locale = makeLocaleTask('src/languages/**/*.po', './dist', 'src');

const watch = (done) => {
	gulp.watch('src/**/*.js', js);
	gulp.watch('src/**/*.css', css);
	gulp.watch('src/**/*.scss', sass);
	gulp.watch('src/**/*.php', php);
	gulp.watch('src/**/*.po', locale);
	done();
};

exports.build   = gulp.parallel(js, css, sass, php, locale);
exports.default = gulp.series(exports.build, watch);
