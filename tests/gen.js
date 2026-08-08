// Generates preview pages that mirror what carousel-3d.php outputs.
const fs = require('fs');
const path = require('path');
const OUT = process.argv[2];

const LABELS = ['Поке з лососем', 'Грецький салат', 'Боул з кіноа', 'Ягідний смузі-боул',
  'Поке з лососем', 'Грецький салат', 'Боул з кіноа', 'Ягідний смузі-боул',
  'Поке з лососем', 'Грецький салат', 'Боул з кіноа', 'Ягідний смузі-боул'];

const VARS = {
  '--c3d-bg-start': '#f0f7f0',
  '--c3d-bg-mid': '#e8f5e8',
  '--c3d-bg-end': '#dcefd4',
  '--c3d-card-bg': '#ffffff',
  '--c3d-card-border': 'rgba(11, 26, 18, 0.08)',
  '--c3d-accent': '#008000',
  '--c3d-title-color': '#1b261b',
  '--c3d-label-color': '#ffffff',
  '--c3d-circle-top': 'rgba(0, 128, 0, 0.06)',
  '--c3d-circle-bottom': 'rgba(0, 128, 0, 0.04)',
  '--c3d-card-aspect': '225/260',
  '--c3d-shadow': '0.35',
};

function page(n, over = {}) {
  const vars = Object.assign({}, VARS, over.vars || {});
  const style = Object.entries(vars).map(([k, v]) => `${k}:${v}`).join(';');
  const cards = [];
  for (let i = 0; i < n; i++) {
    const img = `assets/img/collage_${(i % 4) + 1}.webp`;
    cards.push(
      `<div class="c3d-card"><a href="#" tabindex="-1">` +
      `<img src="${img}" alt="${LABELS[i]}" loading="lazy" decoding="async">` +
      `<span class="c3d-card-label">${LABELS[i]}</span></a></div>`);
  }
  const sceneCls = 'c3d-scene' + (over.floating ? ' c3d-scene--floating' : '');
  return `<!DOCTYPE html><html lang="uk"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>C3D v2 — ${n} cards</title>
<link rel="stylesheet" href="assets/css/carousel-3d.css">
<style>body{margin:0;font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#fff}
 .below{padding:40px;font-size:14px;color:#666}</style>
</head><body>
<section class="c3d-section" id="c3d-1" style="${style}">
 <div class="c3d-container">
  <div class="c3d-title-area">
   <span class="c3d-subtitle">Taste the Difference</span>
   <h2 class="c3d-title">Real Food, Real Results</h2>
   <div class="c3d-accent-line"></div>
  </div>
  <div class="${sceneCls}" tabindex="0" role="group"
    aria-roledescription="3D carousel" aria-label="Real Food, Real Results"
    data-tilt="${over.tilt ?? -10}" data-lean="0" data-wobble="${over.wobble ?? 1.2}"
    data-speed="0.12" data-spread="${over.spread ?? 1.14}"
    data-autoplay="1" data-snap="1" data-hideback="${over.hideback ? 1 : 0}">
   <div class="c3d-ring">
${cards.map(c => '    ' + c).join('\n')}
   </div>
  </div>
  <span class="c3d-hint">Потягніть, щоб роздивитися</span>
 </div>
</section>
<div class="below">(контент нижче секції)</div>
<script src="assets/js/carousel-3d.js"></script>
</body></html>`;
}

for (const n of [2, 3, 4, 5, 6, 8, 12]) {
  fs.writeFileSync(path.join(OUT, `v2_${n}.html`), page(n));
}
fs.writeFileSync(path.join(OUT, 'v2_hideback.html'), page(8, { hideback: true }));
fs.writeFileSync(path.join(OUT, 'v2_floating.html'), page(6, { floating: true }));
console.log('generated');
