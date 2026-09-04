/*
 * Prueba del flujo completo en un navegador real, via Chrome DevTools Protocol.
 *
 * Comprueba las transiciones de pantalla (login -> error falso -> revelacion)
 * y, sobre todo, inspecciona el trafico de red real para verificar que la
 * contrasena no viaja en ningun cuerpo de peticion.
 *
 * Uso:
 *   php -S 127.0.0.1:8399 -t public &
 *   chrome --headless --remote-debugging-port=9222 about:blank &
 *   node bin/browser-test.mjs
 */

const CDP = 'http://127.0.0.1:9222';

const target = await (await fetch(`${CDP}/json/new?about:blank`, { method: 'PUT' })).json();
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));

let id = 0;
const pending = new Map();
ws.onmessage = (m) => {
  const msg = JSON.parse(m.data);
  if (pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
};
const send = (method, params = {}) =>
  new Promise((res) => { const i = ++id; pending.set(i, res); ws.send(JSON.stringify({ id: i, method, params })); });

const evaluate = async (expr) => {
  const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true });
  if (r.result?.exceptionDetails) throw new Error(JSON.stringify(r.result.exceptionDetails));
  return r.result.result.value;
};

const netLog = [];
await send('Network.enable');
ws.addEventListener('message', (m) => {
  const msg = JSON.parse(m.data);
  if (msg.method === 'Network.requestWillBeSent' && msg.params.request.postData)
    netLog.push(msg.params.request.postData);
});

await send('Page.enable');
await send('Page.navigate', { url: 'http://127.0.0.1:8399/index.html' });
await new Promise((r) => setTimeout(r, 1200));

let fails = 0;
const check = (name, cond) => { if (!cond) fails++; console.log(`  ${cond ? 'ok   ' : 'FALLA'} ${name}`); };

const visible = (sel) => evaluate(`(()=>{const e=document.querySelector('${sel}');return !!e && e.offsetParent!==null})()`);

check('arranca mostrando el login', await visible('#screen-login'));
check('la pantalla de error esta oculta', !(await visible('#screen-error')));
check('la revelacion esta oculta', !(await visible('#screen-result')));

const SECRET = 'MiClaveSuperSecreta2024!';
await evaluate(`
  document.getElementById('email').value = 'prueba@empresa.com';
  document.getElementById('password').value = ${JSON.stringify(SECRET)};
  document.getElementById('login-form').requestSubmit();
  'sent'
`);
await new Promise((r) => setTimeout(r, 4000));

check('tras enviar aparece el error falso', await visible('#screen-error'));
check('el login quedo oculto', !(await visible('#screen-login')));
check('el campo de contrasena se limpio', '' === await evaluate(`document.getElementById('password').value`));

await evaluate(`document.getElementById('retry').click(); 'clicked'`);
await new Promise((r) => setTimeout(r, 600));

check('el clic en reintentar revela la simulacion', await visible('#screen-result'));
check('se muestra el aviso del reintento', await visible('#retry-callout'));
check('el error quedo oculto', !(await visible('#screen-error')));

const prefix = await evaluate(`document.getElementById('prefix').textContent`);
check('la huella mostrada es de 16 bits', /^[0-9a-f]{4}$/.test(prefix));

check('la revelacion no ofrece ningun enlace para clickear',
  0 === await evaluate(`document.querySelectorAll('#screen-result a[href]').length`));

// Lo mas importante: la contrasena no aparece en NINGUN cuerpo enviado.
const bodies = netLog.join('\n');
check('la contrasena nunca viajo por la red', !bodies.includes(SECRET));
check('se enviaron peticiones (el test observa trafico real)', netLog.length > 0);

// El beacon del reintento tiene que llegar de verdad al backend: si falla
// en silencio, la metrica del taller queda en cero sin que nadie lo note.
await new Promise((r) => setTimeout(r, 800));
const stats = await evaluate(`fetch('api.php?action=stats').then(r=>r.json())`);
check('el backend registro el intento', 1 === stats.participants);
check('el backend registro el clic en reintentar', 1 === stats.retried);

console.log(`\nhuella=${prefix}  stats=${JSON.stringify(stats)}`);
console.log(fails === 0 ? 'Todo en orden.' : `${fails} comprobacion(es) fallaron.`);
ws.close();
process.exit(fails ? 1 : 0);
