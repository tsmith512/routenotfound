/**
 * Simple script to pull down the header image options and make the CSS and JS
 * outputs to make that work. Also pull down the HFJ typefaces payload to host.
 * Pulls from R2 Storage with awscli --- make sure the R2 endpoint is set in env.
 *
 * @TODO: This really doesn't need to be in gulp anymore...
 */

import * as dotenv from 'dotenv';
dotenv.config();

import gulp from 'gulp';
const { series, parallel } = gulp;

import { deleteSync } from 'del';
import { execSync } from 'node:child_process';
import * as fs from 'fs';

export const clean = async () => {
  return deleteSync([
    'sources/img/headers/original/**/*',
    'dist/**/*'
  ]);
};

export const fetchHeaders = async (cb) => {
  execSync('mkdir -p sources/img/headers/original/'); // Make sure the image download directory exists
  execSync('mkdir -p dist/css dist/js'); // Make sure the output directories exist
  execSync(`aws s3 sync s3://tsmith-website-build-assets/routenotfound-com/header_images sources/img/headers/original/ --endpoint-url ${process.env.R2_ENDPOINT}`, {stdio: 'inherit'});

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
      .header-${filename}                              { background-image: url("/cdn-cgi/image/width=600,quality=75/wp-content/themes/rnf-alfa/sources/img/headers/original/${filename}.jpg"); }
      @media (min-width: 480px)  { .header-${filename} { background-image: url("/cdn-cgi/image/width=760,quality=80/wp-content/themes/rnf-alfa/sources/img/headers/original/${filename}.jpg"); } }
      @media (min-width: 960px)  { .header-${filename} { background-image: url("/cdn-cgi/image/width=1280,quality=85/wp-content/themes/rnf-alfa/sources/img/headers/original/${filename}.jpg"); } }
      @media (min-width: 1280px) { .header-${filename} { background-image: url("/cdn-cgi/image/width=1920,quality=90/wp-content/themes/rnf-alfa/sources/img/headers/original/${filename}.jpg"); } }
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
    })();`.replace(/\s+/g, ' ')
  );

  fs.writeFileSync('dist/css/header-images.css', outputCSS.join('\n').replace(/\s+/g, ' '));
  cb();
};

export const fetchFonts = async (cb) => {
  execSync('mkdir -p dist/webfonts'); // Make sure the output directories exist
  execSync(`aws s3 sync s3://tsmith-website-build-assets/routenotfound-com/webfonts dist/webfonts/ --endpoint-url ${process.env.R2_ENDPOINT}`, {stdio: 'inherit'});

  cb();
};

const build = series(clean, parallel([fetchHeaders, fetchFonts]));
export default build;
