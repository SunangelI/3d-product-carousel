// Loads a real Elementor page carrying the widget and checks the carousel is
// alive on it — and that the teardown the builder relies on actually tears down.
//
// No login: the page is published, so this is exactly what a visitor gets.
// Usage: node tests/elementorlive.js <page_id>

const BASE = 'http://localhost:8081';
const PAGE_ID = process.argv[2];

if (!PAGE_ID) {
	console.error('usage: node tests/elementorlive.js <page_id>');
	process.exit(2);
}

let pass = 0, fail = 0;
const check = (label, cond, detail = '') => {
	if (cond) { pass++; console.log('  OK    ' + label); }
	else { fail++; console.log('  FAIL  ' + label + (detail ? ' — ' + detail : '')); }
};

async function main() {
	const tab = await (await fetch('http://127.0.0.1:9222/json/new?about:blank', { method: 'PUT' })).json();
	const ws = new WebSocket(tab.webSocketDebuggerUrl);
	let id = 0; const pending = new Map();
	const send = (method, params = {}) => new Promise((res, rej) => {
		const mid = ++id; pending.set(mid, { res, rej });
		ws.send(JSON.stringify({ id: mid, method, params }));
	});
	const errors = [];
	ws.onmessage = (ev) => {
		const m = JSON.parse(ev.data);
		if (m.id && pending.has(m.id)) {
			const p = pending.get(m.id); pending.delete(m.id);
			m.error ? p.rej(new Error(m.error.message)) : p.res(m.result);
		}
		if (m.method === 'Runtime.exceptionThrown') {
			errors.push(m.params.exceptionDetails.exception?.description
				|| m.params.exceptionDetails.text);
		}
		if (m.method === 'Runtime.consoleAPICalled' && m.params.type === 'error') {
			errors.push(m.params.args.map(a => a.value || a.description).join(' '));
		}
	};
	await new Promise(r => ws.onopen = r);
	const wait = ms => new Promise(r => setTimeout(r, ms));
	const evalJs = async (expr) => {
		const r = await send('Runtime.evaluate', { expression: expr, returnByValue: true });
		if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || 'eval failed');
		return r.result.value;
	};

	await send('Page.enable'); await send('Runtime.enable');
	await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
	await send('Page.navigate', { url: BASE + '/?page_id=' + PAGE_ID });
	await wait(4000);

	console.log('\n== the page a visitor gets ==');

	check('Elementor rendered the widget', await evalJs(
		`!!document.querySelector('.elementor-widget-carousel_3d')`
	));
	check('the carousel is inside it', await evalJs(
		`!!document.querySelector('.elementor-widget-carousel_3d .c3d-scene')`
	));
	check('the stylesheet applied', await evalJs(
		`(()=>{const c=document.querySelector('.c3d-card');
		 return c && getComputedStyle(c).position === 'absolute';})()`
	), 'cards are not positioned — CSS did not load');
	check('the carousel started', await evalJs(`!!document.querySelector('.c3d-scene').dataset.c3dReady`));
	check('the instance is reachable for teardown', await evalJs(
		`typeof document.querySelector('.c3d-scene')._c3d === 'object'`
	));

	// Cards are sized by measurement, so a real layout pass means a real width.
	const cardW = await evalJs(`document.querySelector('.c3d-card').getBoundingClientRect().width`);
	check('cards were laid out', cardW > 100, cardW + 'px');

	const before = await evalJs(`document.querySelector('.c3d-ring').style.transform`);
	await wait(1200);
	const after = await evalJs(`document.querySelector('.c3d-ring').style.transform`);
	check('the ring is turning', before !== after, before + ' -> ' + after);

	console.log('\n== the teardown a builder needs ==');

	// Elementor throws the old copy away and draws a new one on every change in
	// the panel. Without a working destroy each discarded copy keeps a resize
	// listener and an animation loop for the rest of the session.
	const destroyed = await evalJs(`(()=>{
		const s = document.querySelector('.c3d-scene');
		const inst = s._c3d;
		window.C3D.destroy(document);
		return { raf: inst.rafId, ready: s.dataset.c3dReady, ref: s._c3d };
	})()`);
	check('the animation loop stopped', null === destroyed.raf, String(destroyed.raf));
	check('the ready flag was cleared', undefined === destroyed.ready, String(destroyed.ready));
	check('the instance was released', null === destroyed.ref, String(destroyed.ref));

	const frozen = await evalJs(`document.querySelector('.c3d-ring').style.transform`);
	await wait(900);
	check('a destroyed carousel really stops moving',
		frozen === await evalJs(`document.querySelector('.c3d-ring').style.transform`));

	check('it can be started again', await evalJs(`(()=>{
		window.C3D.init(document);
		return !!document.querySelector('.c3d-scene').dataset.c3dReady;
	})()`));
	await wait(900);
	check('and it moves again',
		frozen !== await evalJs(`document.querySelector('.c3d-ring').style.transform`));

	check('init twice does not double up', await evalJs(`(()=>{
		const s = document.querySelector('.c3d-scene');
		const first = s._c3d;
		window.C3D.init(document);
		return s._c3d === first;
	})()`));

	console.log('\n== nothing broke along the way ==');
	check('no JavaScript errors', errors.length === 0, errors.join(' | '));

	console.log('\n' + '-'.repeat(50));
	console.log(`passed: ${pass}   failed: ${fail}`);
	ws.close();
	process.exit(fail > 0 ? 1 : 0);
}

main().catch(e => { console.error(e); process.exit(1); });
