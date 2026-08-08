// Activates each installed theme in turn and checks the carousel survives it.
//
// The failure this exists to catch: themes very commonly set overflow-x:hidden
// on a wrapper, which per spec forces overflow-y to auto and turns that wrapper
// into a clipping box. In 1.x that sliced the bottom corners off the cards.
//
// Usage:  node tests/themesweep.js [pageId]
// Needs Apache + MySQL running and Chrome on --remote-debugging-port=9222.

const { execFileSync } = require('child_process');
const path = require('path');

const BASE = process.env.C3D_BASE || 'http://localhost:8081';
const PAGE = process.argv[2] || '4';
// A page with no carousel on it, used for the asset-leak check.
const CLEAN_PAGE = process.env.C3D_CLEAN_PAGE || '2';
// The wp wrapper is a shell script, so invoke the phar with PHP directly.
const PHP = process.env.C3D_PHP ||
	'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
const WP_PHAR = process.env.C3D_WP_PHAR || 'C:/wp-cli/wp-cli.phar';
const SITE = process.env.C3D_SITE || 'D:/laragon/www/c3dtest';
const cdp = path.join(__dirname, 'cdp.js');

// Fetched against a page that has no carousel on it. Registering a block with
// 'style'/'script' handles made WordPress enqueue them site-wide on any theme
// that has not opted into per-block asset loading — which classic themes have
// not. That silently undid the conditional loading, and only showed up on a
// real classic-theme site.
const LEAK_PROBE = `(()=>{
  const hit=[...document.querySelectorAll('link[href],script[src]')]
    .map(e=>e.href||e.src).filter(u=>u.includes('carousel-3d'));
  return {carousel:!!document.querySelector('.c3d-scene'), leaked:hit.length,
          which:hit.map(u=>u.split('/').pop().split('?')[0])};
})()`;

const PROBE = `new Promise(res=>{
  const scene=document.querySelector('.c3d-scene');
  if(!scene){res({fatal:'no carousel on page'});return;}
  const sec=scene.closest('.c3d-section');
  const inst=scene._c3d;
  const rot0=inst?inst.rotation:null;
  let worstY=-1e9;
  let frames=0;
  function sample(){
    const s=scene.getBoundingClientRect();
    document.querySelectorAll('.c3d-card').forEach(c=>{
      const b=c.getBoundingClientRect();
      worstY=Math.max(worstY,b.bottom-s.bottom,s.top-b.top);
    });
    if(++frames<150){requestAnimationFrame(sample);return;}

    // Ancestors that would clip us. Our own section clips on purpose.
    const clippers=[];
    let n=sec?sec.parentElement:scene.parentElement;
    while(n&&n!==document.documentElement){
      const cs=getComputedStyle(n);
      if(cs.overflowX!=='visible'||cs.overflowY!=='visible'){
        clippers.push((n.tagName+'.'+(typeof n.className==='string'?n.className.trim().split(/\\s+/)[0]:'')).slice(0,34)
          +' ['+cs.overflowX+'/'+cs.overflowY+']');
      }
      n=n.parentElement;
    }
    const secBox=sec?sec.getBoundingClientRect():null;
    res({
      cards:document.querySelectorAll('.c3d-card').length,
      cardCss:(document.querySelector('.c3d-card')||{style:{}}).style.width+'x'+
              (document.querySelector('.c3d-card')||{style:{}}).style.height,
      overflowY:Math.round(worstY),
      clippers:clippers,
      sectionW:secBox?Math.round(secBox.width):null,
      viewportW:window.innerWidth,
      animating:!!(inst&&inst.rafId&&Math.abs(inst.rotation-rot0)>0.3),
      cssLoaded:!!Array.from(document.styleSheets).find(s=>(s.href||'').includes('carousel-3d.css')),
      errs:(window.__errs||[]).join(' / ')
    });
  }
  requestAnimationFrame(sample);
})`;

function wp(args) {
  return execFileSync(PHP, [WP_PHAR, '--path=' + SITE, ...args], { encoding: 'utf8' })
    .split('\n').filter(l => !/^(Warning|PHP Warning)/i.test(l)).join('\n').trim();
}

const themes = wp(['theme', 'list', '--field=name']).split('\n')
  .map(s => s.trim()).filter(Boolean).sort();

console.log(`Testing ${themes.length} themes against ${BASE}/?page_id=${PAGE}\n`);
const head = 'theme'.padEnd(20) + 'cards  card(css)      overY  section/vw   anim  verdict';
console.log(head);
console.log('-'.repeat(head.length + 20));

const problems = [];
for (const theme of themes) {
  wp(['theme', 'activate', theme]);
  let r;
  try {
    r = JSON.parse(execFileSync('node',
      [cdp, `${BASE}/?page_id=${PAGE}`, '', process.env.C3D_W || '1440', process.env.C3D_H || '1000', '3000', PROBE],
      { encoding: 'utf8' }).trim());
  } catch (e) {
    console.log(theme.padEnd(20) + 'probe failed: ' + e.message.slice(0, 60));
    problems.push(theme + ': probe failed');
    continue;
  }

  if (r.fatal) {
    console.log(theme.padEnd(20) + 'FATAL: ' + r.fatal);
    problems.push(theme + ': ' + r.fatal);
    continue;
  }

  // A clipping ancestor only matters if something actually reaches it. Report
  // it either way — it is the single most useful diagnostic when a theme does
  // cut the carousel — but only fail when a card is genuinely outside the box.
  const issues = [];
  if (r.overflowY > 2) {
    issues.push('CUT OFF by ' + (r.clippers[0] || 'unknown') + ' (' + r.overflowY + 'px outside)');
  }
  if (!r.animating) issues.push('not animating');
  if (!r.cssLoaded) issues.push('css missing');
  if (r.errs) issues.push('JS: ' + r.errs.slice(0, 60));
  // Does the plugin leak its assets onto a page that has no carousel?
  let leak = null;
  if (CLEAN_PAGE) {
    try {
      leak = JSON.parse(execFileSync('node',
        [cdp, `${BASE}/?page_id=${CLEAN_PAGE}`, '', '1440', '1000', '1500', LEAK_PROBE],
        { encoding: 'utf8' }).trim());
    } catch { /* leave leak null */ }
    if (leak && !leak.carousel && leak.leaked > 0) {
      issues.push('LEAKS ' + leak.which.join(',') + ' onto carousel-free pages');
    }
  }

  if (issues.length) problems.push(theme + ': ' + issues.join('; '));

  const note = r.clippers.length
    ? `clips, ${-r.overflowY}px headroom`
    : 'no clipping ancestor';

  console.log(
    theme.padEnd(20) +
    String(r.cards).padStart(3) + '   ' +
    String(r.cardCss).padEnd(14) +
    String(r.overflowY).padStart(5) + '   ' +
    (r.sectionW + '/' + r.viewportW).padEnd(12) +
    (r.animating ? ' yes ' : ' NO  ') + '  ' +
    (issues.length ? issues.join('; ') : 'OK — ' + note)
  );
}

console.log('\n' + '='.repeat(60));
if (problems.length) {
  console.log(`${problems.length} theme(s) with findings:`);
  problems.forEach(p => console.log('  - ' + p));
} else {
  console.log('All themes pass: every card stayed inside the scene box, including');
  console.log('on themes whose wrappers would have clipped it.');
}
