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
- Muestra un error generico con un boton "Volver a intentar". Que hace ese
  boton depende del modo (ver abajo).
- Explica que senales debieron detectarse.
- Trae un panel proyectable con los recuentos para el cierre del taller.
- Permite que cada persona compruebe en el taller que lo capturado corresponde
  a su contrasena real.

No hace, deliberadamente:

- **No clona la pantalla de Microsoft ni de ningun proveedor real.** El diseno
  es corporativo generico. Si necesitas fidelidad de marca, usa Attack
  Simulation Training de Microsoft Defender (ver mas abajo), que trae las
  plantillas licenciadas.
- **No transmite la contrasena.** Ni cifrada, ni hasheada en el servidor, ni
  en ningun formato. El plaintext no sale del navegador.

## Los dos modos

Se eligen en `public/config.js`.

### `reveal` (por defecto)

El boton "Volver a intentar" no lleva a ningun lado: dispara la revelacion en
el acto. El clic es la trampa, y la leccion la da el propio clic en lugar de
una diapositiva. Si la persona no toca nada, la revelacion aparece igual a los
20 segundos.

Es el modo para una **campana por correo**, donde no hay nadie presente para
explicar. La pantalla final no ofrece ningun enlace: pide entrar al portal
escribiendo la direccion a mano, porque seria contradictorio ensenar "verifica
la URL antes de tipear" y cerrar dando un enlace para clickear.

### `handoff` (solo taller presencial)

El boton redirige al login legitimo, donde la persona entra normalmente y no
nota nada. Reproduce lo que hace un ataque real.

Este es el modo mas potente para un taller con la gente en la sala, y la razon
es pedagogica: si la pagina grita "caiste" en el momento, la leccion que se
llevan es que las paginas de phishing se delatan, que es lo contrario de la
verdad. Dejarlos entrar, que funcione, que queden sin entender, y recien
entonces explicar, ensena lo que de verdad importa: **no lo habrias notado**.

**Este modo no explica nada por si mismo.** Depende enteramente de que cierres
la sesion contando que ocurrio. Usalo solo con la gente presente y con el
cierre asegurado; en una campana por correo la gente se queda con la confusion
y sin la leccion, que es lo unico que justifica el ejercicio.

Requiere `realLoginUrl` con una URL **https**. Si falta, no es https o no es
una URL valida, el ejercicio vuelve solo a `reveal` en lugar de mandar a la
gente a un destino inesperado.
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
bin/test-all.sh
```

Corre las cuatro suites — backend y criptografia, validacion de la
configuracion, y el navegador en los dos modos — y deja `config.js` como
estaba. La prueba del navegador maneja un Chromium real por CDP e inspecciona
el trafico para confirmar que la contrasena no sale del equipo. Si tu Chromium
esta en otra ruta, pasala en `CHROME=`.

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

## Guion del taller presencial (modo `handoff`)

Este es el formato mas efectivo: todo ocurre en la misma sesion, con la gente
en la sala.

1. **Preparacion.** `mode: 'handoff'` y `realLoginUrl` apuntando al login real.
   Proyecta `panel.html` en una pantalla que vos veas y ellos no.
2. **La consigna.** Pediles que entren al portal a revisar algo — cualquier
   excusa de trabajo normal. Dales la URL del ejercicio sin darle importancia.
3. **Lo que viven ellos.** Escriben sus credenciales, les da un error, tocan
   "Volver a intentar", caen en el login real, entran sin problema y siguen
   con lo suyo. Nadie nota nada. **Ese es el punto.**
4. **Segui con el taller.** Veinte minutos de contenido normal. Que se olviden.
5. **El cierre.** Proyecta el panel: *"hace media hora, N de ustedes escribieron
   su contrasena en un sitio que no es de la empresa. Y Y de esos hicieron clic
   en 'Volver a intentar'"*.
6. **El mensaje de error.** Detenete en ese segundo numero. No hubo ninguna
   falla tecnica: ese error existe para conseguir un segundo intento, o para
   que la persona crea que se equivoco al tipear. Es la tactica mas vieja del
   manual y funciona porque un error de sistema parece de lo mas normal.
7. **La prueba.** Abri `verificar.html` y pedi un voluntario. Que escriba su
   contrasena. Sale **COINCIDE**.
8. **El giro.** Abri las herramientas de desarrollo, pestana Red, y repeti la
   operacion en vivo: lo unico que viaja son 4 caracteres hexadecimales. Vos
   nunca la tuviste. Un atacante real la tendria completa, en texto plano, y
   ya estaria dentro de la cuenta — y ellos tampoco se habrian enterado.
9. **El cierre.** El unico control que no falla nunca: mirar la barra de
   direcciones **antes** de tipear. Y si el gestor de contrasenas no
   autocompleta, es porque el dominio no es el que dice ser.

El paso 4 no es relleno. La distancia entre el momento en que caen y el momento
en que se enteran es lo que demuestra que un ataque real pasa desapercibido.

## Guion de campana por correo (modo `reveal`)

1. **Antes.** Envia el correo senuelo con el enlace. Dale unos dias.
2. **Cada persona** que cae se entera en el acto, en su pantalla.
3. **En la sala.** Los mismos pasos 5 a 9 de arriba, con los numeros ya cerrados.

Pediles en la pantalla final que no le cuenten a los companeros hasta el
taller — ya viene ese pedido en el texto.

## Antes de ejecutarlo

- Autorizacion **por escrito** de Direccion o RRHH. Que seas gerente de TI no
  alcanza como respaldo si despues alguien reclama.
- Avisale al SOC, al helpdesk y a quien administre el correo, para que no lo
  traten como un incidente real.
- Nunca expongas quien cayo. El agregado sirve; los nombres humillan y hacen
  que la proxima vez nadie reporte nada.
- Si usas el modo `handoff`, **el cierre no es opcional**. Es lo unico que
  convierte el ejercicio en capacitacion en vez de en una broma a costa de la
  gente. No lo dejes para otro dia.
- Avisales que su gestor de contrasenas pudo haber ofrecido guardar el dominio
  falso, y que conviene borrar esa entrada.
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
