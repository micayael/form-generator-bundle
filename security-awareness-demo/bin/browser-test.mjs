/*
 * Prueba del flujo completo en un navegador real, via Chrome DevTools Protocol.
 *
 * Comprueba las transiciones de pantalla en los dos modos y, sobre todo,
 * inspecciona el trafico de red real para verificar que la contrasena no
 * viaja en ningun cuerpo de peticion.
 *
 * Uso:
 *   php -S 127.0.0.1:8399 -t public &
 *   chrome --headless --remote-debugging-port=9222 about:blank &
 *   node bin/browser-test.mjs [reveal|handoff]
 */

const CDP = 'http://127.0.0.1:9222';
const MODE = process.argv[2] || 'reveal';
const HANDOFF_URL = 'https://portal-interno.example.invalid/login';

const target = await (await fetch(`${CDP}/json/new?about:blank`, { method: 'PUT' })).json();
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));

let id = 0;
const pending = new Map();
const navigations = [];
const netLog = [];

ws.onmessage = (m) => {
  const msg = JSON.parse(m.data);

  if (pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
  if (msg.method === 'Page.frameRequestedNavigation') navigations.push(msg.params.url);
  if (msg.method === 'Network.requestWillBeSent' && msg.params.request.postData)
    netLog.push(msg.params.request.postData);
};

const send = (method, params = {}) =>
  new Promise((res) => { const i = ++id; pending.set(i, res); ws.send(JSON.stringify({ id: i, method, params })); });

const evaluate = async (expression) => {
  const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (r.result?.exceptionDetails) throw new Error(JSON.stringify(r.result.exceptionDetails));
  return r.result.result.value;
};

const wait = (ms) => new Promise((r) => setTimeout(r, ms));
const visible = (sel) => evaluate(`(()=>{const e=document.querySelector('${sel}');return !!e && e.offsetParent!==null})()`);

let fails = 0;
const check = (name, cond) => { if (!cond) fails++; console.log(`  ${cond ? 'ok   ' : 'FALLA'} ${name}`); };

await send('Network.enable');
await send('Page.enable');
await send('Page.navigate', { url: 'http://127.0.0.1:8399/index.html' });
await wait(1200);

console.log(`Flujo en el navegador — modo ${MODE}\n`);

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
await wait(4000);

check('tras enviar aparece el error falso', await visible('#screen-error'));
check('el login quedo oculto', !(await visible('#screen-login')));
check('el campo de contrasena se limpio', '' === await evaluate(`document.getElementById('password').value`));

const prefix = await evaluate(`document.getElementById('prefix').textContent`);
check('la huella calculada es de 16 bits', /^[0-9a-f]{4}$/.test(prefix));

// Lo mas importante, y vale para los dos modos: la contrasena no aparece en
// ningun cuerpo enviado. Se observa el trafico real, no el codigo fuente.
check('la contrasena nunca viajo por la red', !netLog.join('\n').includes(SECRET));
check('se enviaron peticiones (el test observa trafico real)', netLog.length > 0);

await evaluate(`document.getElementById('retry').click(); 'clicked'`);
await wait(1000);

if ('handoff' === MODE) {
  check('el boton redirige al login real', navigations.includes(HANDOFF_URL));
  check('no se revela nada en el sitio falso', !(await visible('#screen-result')));
} else {
  check('el clic revela la simulacion', await visible('#screen-result'));
  check('se muestra el aviso del reintento', await visible('#retry-callout'));
  check('el error quedo oculto', !(await visible('#screen-error')));
  check('la revelacion no ofrece ningun enlace para clickear',
    0 === await evaluate(`document.querySelectorAll('#screen-result a[href]').length`));
  check('no hubo ninguna redireccion', 0 === navigations.length);
}

// El beacon del reintento tiene que llegar de verdad al backend: si falla en
// silencio, la metrica del taller queda en cero sin que nadie lo note.
const stats = await (await fetch('http://127.0.0.1:8399/api.php?action=stats')).json();
check('el backend registro el intento', 1 === stats.participants);
check('el backend registro el clic en reintentar', 1 === stats.retried);

console.log(`\nhuella=${prefix}  stats=${JSON.stringify(stats)}`);
console.log(fails === 0 ? 'Todo en orden.' : `${fails} comprobacion(es) fallaron.`);
ws.close();
process.exit(fails ? 1 : 0);
