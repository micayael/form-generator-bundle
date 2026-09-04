#!/usr/bin/env bash
# Corre las cuatro suites: backend, configuracion, y el navegador en los dos
# modos. Deja config.js como estaba al terminar.
set -e
cd "$(dirname "$0")/.."
CFG=public/config.js
cp $CFG "${TMPDIR:-/tmp}"/config.bak

start() {
  rm -f data/participants.json
  php -S 127.0.0.1:8399 -t public > "${TMPDIR:-/tmp}"/srv.log 2>&1 &
  echo $! > "${TMPDIR:-/tmp}"/php.pid
  curl -s --retry-connrefused --retry 30 --retry-delay 1 -o /dev/null "http://127.0.0.1:8399/api.php?action=stats"
}
stop() { kill $(cat "${TMPDIR:-/tmp}"/php.pid) 2>/dev/null || true; }

"${CHROME:-/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell}" \
  --headless --no-sandbox --disable-gpu --remote-debugging-port=9222 about:blank \
  > "${TMPDIR:-/tmp}"/chrome.log 2>&1 &
CH=$!
curl -s --retry-connrefused --retry 30 --retry-delay 1 -o /dev/null http://127.0.0.1:9222/json/version

RC=0
echo "===== 1. Backend y criptografia ====="
start; php bin/selftest.php || RC=1; stop

echo; echo "===== 2. Validacion de configuracion ====="
node bin/config-test.mjs || RC=1

echo; echo "===== 3. Navegador, modo reveal ====="
start; node bin/browser-test.mjs reveal || RC=1; stop

echo; echo "===== 4. Navegador, modo handoff ====="
sed -i "s#mode: 'reveal'#mode: 'handoff'#; s#realLoginUrl: ''#realLoginUrl: 'https://portal-interno.example.invalid/login'#" $CFG
start; node bin/browser-test.mjs handoff || RC=1; stop
cp "${TMPDIR:-/tmp}"/config.bak $CFG

kill $CH 2>/dev/null || true
echo; echo "===== resultado global: $([ $RC -eq 0 ] && echo TODO OK || echo FALLAS) ====="
exit $RC
