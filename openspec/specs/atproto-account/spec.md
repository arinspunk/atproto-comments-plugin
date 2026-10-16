# atproto-account Specification

## Purpose

Define la cuenta de AT Protocol del sitio: cómo se configuran y guardan sus credenciales, cómo se comprueba la conexión y cómo se resuelve un handle a su DID y a su PDS sin fijar ningún proveedor.

## Requirements

### Requirement: Credenciales de la cuenta
La cuenta del sitio SHALL configurarse con dos campos: identificador (handle o DID) y app password. Hay una sola cuenta para todo el sitio. Solo los usuarios con `manage_options` SHALL poder verla o cambiarla.

#### Scenario: Guardar la cuenta
- **WHEN** el administrador escribe `usoritual.com` y una app password y guarda
- **THEN** la cuenta queda configurada y el handle se muestra en los ajustes

#### Scenario: Identificador con DID
- **WHEN** el administrador escribe `did:plc:abc234567abcdefghijklmno` como identificador
- **THEN** se acepta igual que un handle

#### Scenario: Identificador no válido
- **WHEN** el administrador escribe `no es un handle` como identificador
- **THEN** se muestra un error y la cuenta guardada no cambia

### Requirement: Almacenamiento cifrado de la app password
La app password SHALL guardarse cifrada en la base de datos con una clave derivada de `AUTH_KEY`, nunca en claro. Nunca SHALL enviarse al navegador: ni en el HTML de los ajustes, ni en respuestas REST, ni en mensajes de error.

#### Scenario: Valor en la base de datos
- **WHEN** se guarda la app password `abcd-efgh-ijkl-mnop`
- **THEN** la base de datos no contiene esa cadena en ninguna opción

#### Scenario: Campo en los ajustes
- **WHEN** el administrador abre los ajustes con una app password guardada
- **THEN** el campo aparece vacío, con una indicación de que hay una contraseña guardada

#### Scenario: Credenciales fuera de la carga automática
- **WHEN** el administrador guarda la cuenta por primera vez
- **THEN** la opción de la cuenta queda marcada para no cargarse automáticamente en cada petición

#### Scenario: Servidor sin la extensión sodium
- **WHEN** el servidor no tiene cargada la extensión `sodium` de PHP y el administrador guarda la cuenta y prueba la conexión
- **THEN** la contraseña se cifra y se usa igual, sin errores

### Requirement: Conservación y borrado de la app password
Guardar los ajustes con el campo de app password vacío SHALL conservar la contraseña guardada. El administrador SHALL poder olvidar la cuenta, lo que borra el identificador y la contraseña.

#### Scenario: Guardar sin tocar la contraseña
- **WHEN** el administrador cambia otro ajuste y guarda con el campo de contraseña vacío
- **THEN** la app password guardada sigue siendo la misma

#### Scenario: Olvidar la cuenta
- **WHEN** el administrador elige olvidar la cuenta
- **THEN** el identificador y la contraseña se borran y la cuenta queda sin configurar

### Requirement: Clave de cifrado cambiada
Si la app password guardada no puede descifrarse (por ejemplo, porque `AUTH_KEY` ha cambiado), la cuenta SHALL tratarse como no configurada y los ajustes SHALL pedir que se vuelva a escribir la contraseña. No SHALL producirse un error fatal.

#### Scenario: AUTH_KEY rotada
- **WHEN** se cambia `AUTH_KEY` en `wp-config.php` y el administrador abre los ajustes
- **THEN** se muestra un aviso que pide volver a introducir la app password

### Requirement: Prueba de conexión
Los ajustes SHALL ofrecer un botón que comprueba la cuenta guardada: resuelve la identidad y abre sesión en su PDS. El resultado SHALL mostrarse sin recargar la página: en caso de éxito, el handle, el DID y el PDS; en caso de fallo, un mensaje traducido con el motivo.

#### Scenario: Conexión correcta
- **WHEN** el administrador pulsa el botón con una cuenta válida
- **THEN** ve un mensaje de éxito con el handle, el DID y la URL del PDS

#### Scenario: Contraseña incorrecta
- **WHEN** el administrador pulsa el botón y el PDS rechaza la app password
- **THEN** ve un mensaje que indica que las credenciales no son válidas, sin mostrar la contraseña

#### Scenario: Sin cuenta configurada
- **WHEN** el administrador pulsa el botón sin cuenta guardada
- **THEN** ve un mensaje que indica que primero hay que guardar la cuenta

### Requirement: Resolución de handle a DID
Un handle SHALL resolverse a DID por los métodos estándar de AT Protocol (registro DNS `_atproto` o `https://<handle>/.well-known/atproto-did`), sin depender de un PDS o AppView fijo.

#### Scenario: Handle con registro DNS
- **WHEN** se resuelve un handle cuyo dominio publica `_atproto.<handle>` con `did=did:plc:…`
- **THEN** se obtiene ese DID

#### Scenario: Handle sin resolución
- **WHEN** se resuelve un handle que no publica DID por ninguno de los métodos
- **THEN** se obtiene un error traducido que indica que el handle no existe o no se puede resolver

### Requirement: Resolución de DID a PDS
El documento DID SHALL obtenerse de `plc.directory` para `did:plc` y de `https://<dominio>/.well-known/did.json` para `did:web`. El PDS SHALL ser el `serviceEndpoint` del servicio `#atproto_pds`. No SHALL fijarse `bsky.social` ni ningún otro PDS en el código.

#### Scenario: Cuenta en un PDS que no es Bluesky
- **WHEN** se resuelve una cuenta alojada en Eurosky
- **THEN** el PDS obtenido es el de Eurosky y las peticiones de sesión van a ese PDS

#### Scenario: Documento sin PDS
- **WHEN** el documento DID no tiene un servicio `#atproto_pds`
- **THEN** se obtiene un error traducido y no se intenta abrir sesión

### Requirement: Verificación bidireccional del handle
Al resolver un handle para la cuenta del sitio, el documento DID SHALL declarar ese handle en `alsoKnownAs` (`at://<handle>`). Si no lo declara, la resolución SHALL fallar.

#### Scenario: Handle no declarado
- **WHEN** el handle apunta a un DID cuyo documento no incluye `at://<handle>` en `alsoKnownAs`
- **THEN** la prueba de conexión falla con un mensaje que indica que el handle no está verificado

### Requirement: Errores de red
Todas las llamadas de red de la cuenta SHALL tener un tiempo de espera limitado. Un tiempo agotado, un error HTTP o una respuesta no válida SHALL convertirse en un mensaje traducido, sin errores fatales ni detalles internos.

#### Scenario: PDS que no responde
- **WHEN** el PDS no responde dentro del tiempo de espera
- **THEN** se muestra un mensaje traducido que indica que el servidor no responde
