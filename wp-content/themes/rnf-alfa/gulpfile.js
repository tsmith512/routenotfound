'use strict';

/**
 * @file build-headers.js
 *
 * Fetch RNF header images from the S3 bucket they live in,
 * (TODO) optimize them for web display across a few screen sizes,
 * generate a CSS index that matches class names to actual images, and
 * output a JS file that WordPress can include which automatically assigns one
 * on DOMContentLoaded.
 */

const gulp = require('gulp');
const del = require('del');
const exec = require('child_process').exec;
const fs = require('fs');

gulp.task('dist-clean', () => {
  return del([
    'sources/img/headers/original/**/*',
    'dist/**/*'
  ]);
});

gulp.task('header-images-fetch', (cb) => {
  exec('mkdir -p sources/img/headers/original/'); // Make sure the image download directory exists
  exec('mkdir -p dist/css dist/js'); // Make sure the output directories exist
  exec('AWS_CREDENTIAL_FILE=~/.aws/credentials s3cmd get s3://routenotfound-assets/header_images/* sources/img/headers/original/', () => {
    const headerImages = [];
    const outputCSS = [];
    const outputList = [];

    const directory = fs.readdirSync('sources/img/headers/original/');

    for (var i = 0; i < directory.length; i++) {
      const file = directory[i];
      headerImages.push(file.replace(/\.\w{3}/, ''));
    }

    headerImages.forEach((filename, index) => {
      outputCSS.push(`
        .header-${filename}                              { background-image: url("/cdn-cgi/image/width=600,quality=80/wp-content/themes/rnf-alfa/sources/img/headers/original/${filename}.jpg"); }
        @media (min-width: 480px)  { .header-${filename} { background-image: url("/cdn-cgi/image/width=760,quality=80/wp-content/themes/rnf-alfa/sources/img/headers/original/${filename}.jpg"); } }
        @media (min-width: 960px)  { .header-${filename} { background-image: url("/cdn-cgi/image/width=1280,quality=80/wp-content/themes/rnf-alfa/sources/img/headers/original/${filename}.jpg"); } }
        @media (min-width: 1280px) { .header-${filename} { background-image: url("/cdn-cgi/image/width=1920,quality=80/wp-content/themes/rnf-alfa/sources/img/headers/original/${filename}.jpg"); } }
      `);
      outputList.push('header-' + filename);
    });

    fs.writeFileSync('dist/js/header-images.js',
      `(function(){
        'use strict';
        document.addEventListener("DOMContentLoaded", () => {
          const headerImages = ${JSON.stringify(outputList)};
          const selectedImage = headerImages[Math.floor(Math.random() * headerImages.length)];
          document.querySelector(\'.custom-header\').classList.add(selectedImage);
        });
      })()`
    );

    fs.writeFileSync('dist/css/header-images.css', outputCSS.join('\n'));
    cb();
  });
});

gulp.task('webfonts-fetch', (cb) => {
  exec('mkdir -p dist/webfonts'); // Make sure the output directories exist
  exec('AWS_CREDENTIAL_FILE=~/.aws/credentials s3cmd get --recursive s3://routenotfound-assets/webfonts/ dist/webfonts/', () => { cb(); });
});

gulp.task('build',
  gulp.series('dist-clean',
    gulp.parallel(
      'header-images-fetch',
      'webfonts-fetch'
    )
  )
);
