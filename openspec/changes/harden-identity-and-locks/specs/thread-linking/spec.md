# Spec Delta

## ADDED Requirements

### Requirement: Resolución de handles con tiempo limitado
Al guardar un vínculo con handle, desde el editor de bloques o el clásico, la resolución del handle SHALL tener un tiempo máximo. Si el handle no se puede resolver, el mismo handle no SHALL volver a consultarse en la red durante 60 segundos, y en ese tiempo SHALL responderse el mismo error.

#### Scenario: Handle que no responde
- **WHEN** el editor pega un enlace con un handle cuyo dominio no responde
- **THEN** el panel muestra el error de handle no resuelto en un tiempo limitado y el vínculo no cambia

#### Scenario: Repetir un handle que falló
- **WHEN** el editor vuelve a pegar el mismo enlace con handle a los pocos segundos de que fallara
- **THEN** recibe el mismo error sin que el servidor vuelva a consultar la red

#### Scenario: Handle que resuelve por DNS
- **WHEN** el editor pega un enlace con un handle de dominio propio que solo publica el registro DNS `_atproto`
- **THEN** el vínculo se guarda con el DID de ese registro

### Requirement: Longitud del DID web
Un DID `did:web` SHALL aceptarse solo si su dominio tiene como mucho 253 caracteres, en el editor, en el servidor y en la API REST.

#### Scenario: DID web demasiado largo
- **WHEN** el editor pega un `at://` con un `did:web` cuyo dominio tiene 254 caracteres
- **THEN** el vínculo no cambia y se muestra un error de formato
