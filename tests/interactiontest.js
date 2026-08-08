// Exercises drag, keyboard, lightbox and resize, then reports any JS errors.
const { execFileSync } = require('child_process');
const path = require('path');

const PROBE = `new Promise(async res=>{
  const log=[];
  const scene=document.querySelector('.c3d-scene');
  const inst=scene._c3d;
  const wait=ms=>new Promise(r=>setTimeout(r,ms));
  const pe=(t,x,y,el)=>(el||scene).dispatchEvent(new PointerEvent(t,
      {bubbles:true,cancelable:true,pointerId:1,clientX:x,clientY:y}));

  if(!inst){res({fatal:'no instance'});return;}
  const r=scene.getBoundingClientRect();
  const cx=r.left+r.width/2, cy=r.top+r.height/2;

  // --- drag ---
  const before=inst.rotation;
  pe('pointerdown',cx,cy); await wait(20);
  for(let i=1;i<=6;i++){pe('pointermove',cx+i*18,cy);await wait(16);}
  pe('pointerup',cx+108,cy); await wait(60);
  log.push('drag rotated: '+(Math.abs(inst.rotation-before)>5));
  log.push('drag inertia: '+(Math.abs(inst.velocity)>0));

  // --- snap settles ---
  await wait(2200);
  const step=inst.step;
  const off=Math.abs(inst.rotation/step-Math.round(inst.rotation/step));
  log.push('snapped to card: '+(off<0.02));

  // --- keyboard ---
  const kbBefore=inst.rotation;
  scene.focus();
  scene.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowLeft',bubbles:true}));
  await wait(900);
  log.push('arrow key moved: '+(Math.abs(inst.rotation-kbBefore)>1));

  // --- lightbox via click (tiny pointer movement) ---
  const card=document.querySelector('.c3d-card');
  pe('pointerdown',cx,cy,card); await wait(20); pe('pointerup',cx+1,cy,card);
  await wait(300);
  const ov=document.querySelector('.c3d-overlay');
  log.push('lightbox opened: '+!!ov);
  if(ov){
    log.push('has media: '+!!ov.querySelector('.c3d-overlay-media'));
    log.push('scroll locked: '+document.body.classList.contains('c3d-scroll-lock'));
    log.push('focus inside: '+ov.contains(document.activeElement));
    document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
    await wait(700);
    log.push('lightbox closed: '+!document.querySelector('.c3d-overlay'));
    log.push('scroll unlocked: '+!document.body.classList.contains('c3d-scroll-lock'));
  }

  // --- resize relayout ---
  const w0=inst.cardW;
  window.dispatchEvent(new Event('resize'));
  await wait(400);
  log.push('survived resize: '+(typeof inst.cardW==='number'&&inst.cardW>0));

  // --- auto-rotation resumes once the post-interaction pause expires ---
  await wait(3600);
  const rr=inst.rotation; await wait(600);
  log.push('autoplay resumed: '+(Math.abs(inst.rotation-rr)>0.1));
  log.push('loop alive: '+(inst.rafId!==null));

  res({log:log,errs:(window.__errs||[]).join(' / ')});
})`;

const cdp = path.join(__dirname, 'cdp.js');
for (const [w, h, name] of [[1440, 1000, 'desktop'], [390, 844, 'phone']]) {
  const out = execFileSync('node', [cdp, 'http://localhost:8777/v2_8.html', '', String(w), String(h), '2500', PROBE],
    { encoding: 'utf8' });
  const r = JSON.parse(out.trim());
  console.log('=== ' + name + ' ===');
  (r.log || [r.fatal]).forEach(l => console.log('  ' + l));
  console.log('  JS errors: ' + (r.errs || 'none'));
}
