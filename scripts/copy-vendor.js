// Copies the frontend libraries the PHP pages load from node_modules/ into vendor/.
// Dependency-free replacement for the old `gulp vendor` task (gulp-sass 4 / node-sass
// no longer builds on current Node). Composer packages in vendor/ are left untouched.
//
// Usage: npm run vendor            (writes to ./vendor)
//        node scripts/copy-vendor.js <outDir>

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const nm = path.join(root, 'node_modules');
const out = path.resolve(root, process.argv[2] || 'vendor');

// [source dir in node_modules, destination dir in vendor, filter(fileName) or null]
const isJs = (f) => f.endsWith('.js');
const copies = [
  ['bootstrap/dist/js', 'bootstrap/js', null],
  ['bootstrap/scss', 'bootstrap/scss', null],
  ['chart.js/dist', 'chart.js', isJs],
  ['datatables.net/js', 'datatables', isJs],
  ['datatables.net-bs4/js', 'datatables', isJs],
  ['datatables.net-bs4/css', 'datatables', (f) => f.endsWith('.css')],
  ['@fortawesome/fontawesome-free', 'fontawesome-free', null],
  ['jquery.easing', 'jquery-easing', isJs],
  ['jquery/dist', 'jquery', (f) => f !== 'core.js'],
];

// The folders this script owns; they are cleared first so stale files don't linger.
const owned = [...new Set(copies.map(([, dest]) => dest.split('/')[0]))];

function copyDir(src, dest, filter) {
  fs.mkdirSync(dest, { recursive: true });
  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    const from = path.join(src, entry.name);
    if (entry.isDirectory()) {
      // Filters only apply to top-level files, matching the old gulp globs
      if (!filter) copyDir(from, path.join(dest, entry.name), null);
    } else if (!filter || filter(entry.name)) {
      fs.copyFileSync(from, path.join(dest, entry.name));
    }
  }
}

if (!fs.existsSync(nm)) {
  console.error('node_modules/ not found. Run `npm ci` (or `npm install`) first.');
  process.exit(1);
}

for (const dir of owned) {
  fs.rmSync(path.join(out, dir), { recursive: true, force: true });
}

for (const [src, dest, filter] of copies) {
  const from = path.join(nm, src);
  if (!fs.existsSync(from)) {
    console.error(`Missing ${path.relative(root, from)}. Is it listed in package.json?`);
    process.exit(1);
  }
  copyDir(from, path.join(out, dest), filter);
  console.log(`${src} -> ${path.relative(root, path.join(out, dest))}`);
}
