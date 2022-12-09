/**
 * Gulpfile
 *
 * @author Takuto Yanagida
 * @version 2022-12-09
 */

import gulp from 'gulp';

import { makeJsTask } from './gulp/task-js.mjs';
import { makeSassTask } from './gulp/task-sass.mjs';
import { makeCopyTask } from './gulp/task-copy.mjs';
import { makeLocaleTask }  from './gulp/task-locale.mjs';

const js_raw  = makeJsTask(['src/**/*.js', '!src/**/*.min.js'], './dist', 'src');
const js_copy = makeCopyTask('src/**/*.min.js', './dist');
const js      = gulp.parallel(js_raw, js_copy);

const css_copy = makeCopyTask('src/**/*.min.css', './dist');
const css      = gulp.parallel(css_copy);

const sass   = makeSassTask('src/**/*.scss', './dist');
const php    = makeCopyTask('src/**/*.php', './dist');
const locale = makeLocaleTask('src/languages/**/*.po', './dist', 'src');

const watch = done => {
	gulp.watch('src/**/*.js', js);
	gulp.watch('src/**/*.css', css);
	gulp.watch('src/**/*.scss', sass);
	gulp.watch('src/**/*.php', php);
	gulp.watch('src/**/*.po', locale);
	done();
};

export const build = gulp.parallel(js, css, sass, php, locale);
export default gulp.series(build, watch);
