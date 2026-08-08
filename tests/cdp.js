// Minimal CDP driver: navigate, emulate viewport, wait, screenshot, eval.
const fs = require('fs');
const path = require('path');

const [, , url, outFile, wStr, hStr, waitStr, evalExpr] = process.argv;
const W = +wStr || 1440, H = +hStr || 1000, WAIT = +waitStr || 3000;

async function main() {
  const list = await (await fetch('http://127.0.0.1:9222/json/new?about:blank', { method: 'PUT' })).json();
  const ws = new WebSocket(list.webSocketDebuggerUrl);
  let id = 0;
  const pending = new Map();
  const send = (method, params = {}) => new Promise((res, rej) => {
    const mid = ++id;
    pending.set(mid, { res, rej });
    ws.send(JSON.stringify({ id: mid, method, params }));
  });
  ws.onmessage = (ev) => {
    const m = JSON.parse(ev.data);
    if (m.id && pending.has(m.id)) {
      const p = pending.get(m.id); pending.delete(m.id);
      m.error ? p.rej(new Error(m.error.message)) : p.res(m.result);
    }
  };
  await new Promise(r => ws.onopen = r);

  // Capture uncaught errors before any page script runs — a throw inside the
  // carousel constructor silently disables everything downstream.
  await send('Page.addScriptToEvaluateOnNewDocument', {
    source: 'window.__errs=[];addEventListener("error",e=>__errs.push(String(e.message)));' +
      'addEventListener("unhandledrejection",e=>__errs.push("rejection: "+e.reason));'
  });

  await send('Emulation.setDeviceMetricsOverride', {
    width: W, height: H, deviceScaleFactor: 1, mobile: W < 700,
  });
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Page.navigate', { url });
  await new Promise(r => setTimeout(r, WAIT));

  if (evalExpr) {
    const r = await send('Runtime.evaluate', { expression: evalExpr, returnByValue: true, awaitPromise: true });
    console.log(JSON.stringify(r.result.value, null, 1));
  }
  if (outFile) {
    const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
    fs.writeFileSync(outFile, Buffer.from(shot.data, 'base64'));
    console.log('wrote ' + outFile);
  }
  await send('Page.close').catch(() => {});
  ws.close();
}
main().catch(e => { console.error(e); process.exit(1); });
