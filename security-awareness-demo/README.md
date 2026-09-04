# Simulacion de phishing para taller de concientizacion

Ejercicio interno para demostrar, ante los propios empleados, lo facil que es
entregar una contrasena corporativa en un sitio que no es el de la empresa.

La demostracion incluye el momento fuerte que suele pedirse — *"mira, tenemos
tu contrasena"* — pero construida de forma que **esa afirmacion sea falsa a
proposito**, y se pueda probar que lo es. Ese es el punto: la leccion es mas
fuerte cuando podes decirle a la gente, con la matematica en la mano, que ni
siquiera vos pudiste quedartela.

## Que hace y que no hace

Hace:

- Presenta una pantalla de inicio de sesion corporativa creible.
- Detecta quien escribio sus credenciales y quien no.
- Muestra un error generico con un boton "Volver a intentar" que **no lleva a
  ningun lado**: el clic dispara la revelacion. Quien pica aprende la leccion
  con su propio clic, no con una diapositiva.
- Muestra en el acto que era una simulacion, con las senales que debieron
  detectarse.
- Permite que cada persona compruebe en el taller que lo capturado corresponde
  a su contrasena real.

No hace, deliberadamente:

- **No clona la pantalla de Microsoft ni de ningun proveedor real.** El diseno
  es corporativo generico. Si necesitas fidelidad de marca, usa Attack
  Simulation Training de Microsoft Defender (ver mas abajo), que trae las
  plantillas licenciadas.
- **No transmite la contrasena.** Ni cifrada, ni hasheada en el servidor, ni
  en ningun formato. El plaintext no sale del navegador.
- **No reenvia al login real.** Ni con un error falso, ni desde el boton de
  reintento, ni con un enlace en la pantalla final. Prolongar el engano es
  tactica de atacante, pero ademas seria contradictorio: no se puede ensenar
  "verifica la URL antes de tipear" y cerrar el ejercicio dando un enlace para
  hacer clic y tipear la contrasena otra vez. La pantalla final pide
  explicitamente entrar al portal real escribiendo la direccion a mano.
- **No almacena nada que permita recuperar una contrasena.**

## Como funciona el hasheo

El problema con "lo hasheo en el servidor" es que la contrasena igual viaja en
texto plano dentro del POST, y en el camino queda expuesta a logs de nginx, el
reverse proxy, un WAF, APM/Sentry, un core dump y la memoria del proceso. La
promesa de no guardarla es una afirmacion sobre la base de datos, no sobre el
sistema.

Aca hay dos decisiones que cierran ese agujero:

**1. El hash se calcula en el navegador.** `crypto.js` deriva
PBKDF2-HMAC-SHA256, 310.000 iteraciones, con un salt aleatorio de 128 bits por
persona. El servidor jamas ve la contrasena, asi que no puede filtrarla.

**2. Se guardan solo 16 bits del hash.** Esto es lo que hace segura la
demostracion:

- La comparacion del taller funciona igual: la persona tipea su contrasena y
  coincide.
- Pero el dato guardado no identifica ninguna contrasena. Con 16 bits, 1 de
  cada 65.536 candidatos colisiona: un diccionario de 14 millones de palabras
  devuelve unas 200 coincidencias y no hay forma de distinguir cual era. No es
  que sea dificil de revertir — es que **no hay informacion suficiente** para
  revertirlo.
- Falso positivo en la demo: 1/65.536 por persona. Con 30 asistentes, 0,05%.

El backend rechaza (422) cualquier huella que no mida exactamente 4 caracteres
hexadecimales. Esa validacion es lo que sostiene la garantia aunque alguien
manipule el cliente: no hay forma de que este servidor acepte un hash completo
ni una contrasena. `bin/selftest.php` lo comprueba.

## Puesta en marcha

```bash
php -S 0.0.0.0:8000 -t public
```

En produccion, detras de HTTPS (sin certificado valido el navegador bloquea
`crypto.subtle`, que solo existe en contextos seguros).

Comprobar que todo funciona. La primera prueba cubre el backend y la
criptografia; la segunda maneja un Chromium real y verifica, inspeccionando el
trafico, que la contrasena no sale del navegador:

```bash
php -S 127.0.0.1:8399 -t public &
php bin/selftest.php

chrome --headless --remote-debugging-port=9222 about:blank &
node bin/browser-test.mjs
```

Borrar los datos apenas termina el taller:

```bash
php bin/purge.php
```

Los registros caducan solos a los 30 dias y el ejercicio se corta a los 200
participantes.

### Dominio

Usa un dominio o subdominio **que tu empresa ya posea** (por ejemplo
`portal-acceso.tudominio.com`). No registres un dominio parecido al de
Microsoft ni a uno ajeno: eso deja de ser un ejercicio interno y pasa a ser
suplantacion de un tercero, con las consecuencias legales del caso.

## Guion del taller

1. **Antes.** Envia el correo senuelo con el enlace. Dale unos dias.
2. **En la sala.** Mostra `api.php?action=stats`: *"de N personas, X escribieron
   su contrasena, y de esas, Y hicieron clic en 'Volver a intentar'"*. Sin
   nombres. Nunca senales a nadie.
3. **El mensaje de error.** Detenete en el numero de reintentos. No hubo ninguna
   falla tecnica: ese error existe para conseguir un segundo intento, o para
   que la persona crea que se equivoco al tipear. Es la tactica mas vieja del
   manual y sigue funcionando porque un error de sistema parece normal.
4. **El momento.** Abri `verificar.html` y pedi un voluntario que haya caido.
   Que escriba su contrasena. Sale **COINCIDE**.
5. **El giro.** Abri las herramientas de desarrollo, pestana Red, y repeti la
   operacion en vivo: lo unico que viaja son 4 caracteres hexadecimales.
   Explica que vos nunca la tuviste — pero que un atacante real la tendria
   completa, en texto plano, y ya estaria dentro de la cuenta.
6. **El cierre.** El unico control que no falla nunca: mirar la barra de
   direcciones **antes** de tipear. Y si el gestor de contrasenas no
   autocompleta, es porque el dominio no es el que dice ser.

## Antes de ejecutarlo

- Autorizacion **por escrito** de Direccion o RRHH. Que seas gerente de TI no
  alcanza como respaldo si despues alguien reclama.
- Avisale al SOC, al helpdesk y a quien administre el correo, para que no lo
  traten como un incidente real.
- Nunca expongas quien cayo. El agregado sirve; los nombres humillan y hacen
  que la proxima vez nadie reporte nada.
- Corre `bin/purge.php` al terminar.

## Alternativa recomendada

Si tu empresa tiene Microsoft 365 con Defender for Office 365 Plan 2 o E5, hay
una opcion mejor que esto: **Attack Simulation Training**, en
`security.microsoft.com` → *Email & collaboration* → *Attack simulation
training*. Trae plantillas de marca licenciadas, metricas por usuario,
asignacion automatica de capacitacion, y tampoco almacena la contrasena
tecleada. Y al ser una herramienta sancionada del tenant, el respaldo
institucional ya viene resuelto.

Este proyecto es util cuando no tenes esa licencia, o cuando queres
especificamente la demostracion de la huella en vivo, que Defender no hace.
