/**
 *
 * Function for gulp (SASS)
 *
 * @author Takuto Yanagida
 * @version 2022-03-23
 *
 */

'use strict';

const SASS_OUTPUT_STYLE = 'compressed';  // 'expanded' or 'compressed'

const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const $    = require('gulp-load-plugins')({ pattern: ['gulp-plumber', 'gulp-autoprefixer', 'gulp-rename', 'gulp-changed'] });

const plumberOptions = {
	errorHandler: function (err) {
		console.log(err.messageFormatted ?? err);
		this.emit('end');
	}
};

function makeSassTask(src, dest = './dist', base = null, addPostfix = true) {
	const sassTask = () => gulp.src(src, { base: base, sourcemaps: true })
		.pipe($.plumber(plumberOptions))
		.pipe(sass.sync({ outputStyle: SASS_OUTPUT_STYLE }))
		.pipe($.autoprefixer({ remove: false }))
		.pipe($.rename({ extname: addPostfix ? '.min.css' : '.css' }))
		.pipe($.changed(dest, { hasChanged: $.changed.compareContents }))
		.pipe(gulp.dest(dest, { sourcemaps: '.' }));
	return sassTask;
}

exports.makeSassTask = makeSassTask;
