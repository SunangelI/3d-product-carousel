// Samples card bounding boxes across a full sway cycle and reports the worst
// overflow out of the scene box, for every card count and viewport.
const { execFileSync } = require('child_process');
const path = require('path');

const PROBE = `new Promise(res=>{
  const scene=document.querySelector('.c3d-scene');
  const inst=scene._c3d;
  const rot0=inst?inst.rotation:null;
  let worst={below:-1e9,above:-1e9,left:-1e9,right:-1e9};
  let frames=0;
  function sample(){
    const s=scene.getBoundingClientRect();
    document.querySelectorAll('.c3d-card').forEach(c=>{
      const b=c.getBoundingClientRect();
      worst.below=Math.max(worst.below,b.bottom-s.bottom);
      worst.above=Math.max(worst.above,s.top-b.top);
      worst.left=Math.max(worst.left,s.left-b.left);
      worst.right=Math.max(worst.right,b.right-s.right);
    });
    if(++frames<260) requestAnimationFrame(sample);
    else {
      const c=document.querySelector('.c3d-card');
      res({n:document.querySelectorAll('.c3d-card').length,
        spinning: !!(inst&&inst.rafId&&Math.abs(inst.rotation-rot0)>0.5),
        errs:(window.__errs||[]).join(' / '),
        card:c.style.width+'x'+c.style.height,
        below:Math.round(worst.below),above:Math.round(worst.above),
        left:Math.round(worst.left),right:Math.round(worst.right),
        sceneH:Math.round(scene.getBoundingClientRect().height),
        sectionH:Math.round(document.querySelector('.c3d-section').getBoundingClientRect().height)});
    }
  }
  requestAnimationFrame(sample);
})`;

const cdp = path.join(__dirname, 'cdp.js');
const viewports = [[1440, 1000, 'desktop'], [768, 1024, 'tablet'], [390, 844, 'phone']];
const pages = ['v2_2', 'v2_3', 'v2_4', 'v2_5', 'v2_6', 'v2_8', 'v2_12'];

console.log('viewport  page    n  card(css)     below above  left right  sceneH sectionH  verdict');
let bad = 0;
for (const [w, h, name] of viewports) {
  for (const p of pages) {
    const out = execFileSync('node', [cdp, `http://localhost:8777/${p}.html`, '', String(w), String(h), '2600', PROBE],
      { encoding: 'utf8' });
    const r = JSON.parse(out.trim());
    const over = Math.max(r.below, r.above);
    const problems = [];
    if (over > 2) problems.push('OVERFLOW ' + over);
    if (!r.spinning) problems.push('NOT ANIMATING');
    if (r.errs) problems.push('JS ERROR: ' + r.errs);
    if (problems.length) bad++;
    console.log(
      name.padEnd(9), p.padEnd(7), String(r.n).padStart(2), r.card.padEnd(13),
      String(r.below).padStart(5), String(r.above).padStart(5),
      String(r.left).padStart(5), String(r.right).padStart(5),
      String(r.sceneH).padStart(6), String(r.sectionH).padStart(8),
      ' ', problems.length ? problems.join('; ') : 'OK');
  }
}
console.log(bad === 0 ? '\nALL PASS — no card leaves the scene box.' : `\n${bad} FAILING configs.`);
