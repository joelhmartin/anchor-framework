// build.mjs — esbuild entry for Anchor Framework CSS/JS pipeline.
// Single command: `npm run build` produces minified dist/ output.
// `npm run watch` rebuilds on change.

import { build, context } from 'esbuild';
import { mkdir } from 'node:fs/promises';

const watch = process.argv.includes('--watch');

const sharedCSS = {
  bundle: true,
  minify: true,
  loader: { '.css': 'css' },
};

const targets = [
  // Existing CSS — utilities
  {
    entryPoints: ['assets/css/utilities.css'],
    outfile: 'dist/utilities.min.css',
    ...sharedCSS,
  },

  // Existing JS — site
  {
    entryPoints: ['assets/js/site.js'],
    outfile: 'dist/site.min.js',
    bundle: true,
    minify: true,
    format: 'iife',
    target: ['chrome90', 'firefox88', 'safari14'],
  },

  // NEW — admin IDE bundle (Phase 4A)
  {
    entryPoints: ['assets/editor/js/ide.js'],
    outfile: 'dist/ide.min.js',
    bundle: true,
    minify: true,
    format: 'iife',
    target: ['chrome90', 'firefox88', 'safari14'],
  },

  // NEW — front-end pencil overlay bundle (Phase 4A)
  {
    entryPoints: ['assets/editor/js/pencil.js'],
    outfile: 'dist/pencil.min.js',
    bundle: true,
    minify: true,
    format: 'iife',
    target: ['chrome90', 'firefox88', 'safari14'],
  },
];

await mkdir('dist', { recursive: true });

if (watch) {
  for (const t of targets) {
    const ctx = await context(t);
    await ctx.watch();
  }
  console.log('[anchor-framework] watching for changes…');
} else {
  for (const t of targets) {
    await build(t);
  }
  console.log('[anchor-framework] build complete.');
}
