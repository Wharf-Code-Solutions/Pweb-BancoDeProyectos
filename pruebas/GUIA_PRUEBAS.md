# Guía de pruebas · Banco de Proyectos

Esta guía explica cómo cargar los datos de prueba y qué hacer para probar cada flujo del sistema con ellos.

## 1. Cargar los datos

Con Apache y MySQL encendidos en XAMPP, desde la carpeta del proyecto:

```
C:\xampp\php\php.exe sql\sembrar.php --confirmar
```

El script **borra la base `banco_proyectos` y la vuelve a crear** con `sql/banco_proyectos.sql`, `sql/datos_prueba.sql` y los escenarios de esta guía. Se puede correr cuantas veces se quiera: cada corrida deja todo como al principio, así que después de probar un flujo (que cambia los datos) basta con volver a correrlo.

- `--base=otra_base` usa otra base en lugar de la de `bd/config.local.php`.
- `--mysql=ruta\mysql.exe` si MySQL no está en `C:\xampp\mysql\bin`.

Las pruebas automáticas (`pruebas\flujo.php`) no dependen de este script: siguen usando solo `datos_prueba.sql` en su propia base temporal.

Sitio: http://localhost/Banco_Proyectos/

> En el servidor de Hostinger solo se cargan las 4 cuentas base de `sql/datos_prueba.sql` (admin, empresa, alumno y profesor), sin los escenarios de esta guía: `sembrar.php` solo funciona en XAMPP. Ver `docs/DESPLIEGUE_HOSTINGER.md`.

## 2. Cuentas

**Todas las cuentas usan la contraseña `Prueba2026!`**

| Correo | Rol | Nombre | Clave | Situación de partida |
|---|---|---|---|---|
| admin@banco.test | Administrador | Administrador de Prueba | ADM001 | Tiene pendientes en las tres bandejas de revisión |
| empresa@banco.test | Empresa | Tecnología de Ejemplo | TDE200101AB1 | Proyectos en todos los estados; candidatos por evaluar |
| empresa2@banco.test | Empresa | Logística del Bajío | LDB190315XY2 | Un proyecto en revisión y tres publicados |
| alumno@banco.test | Alumno | Alumno de Prueba | 20260001 | 1 candidatura pendiente, 2 canceladas; puede postularse |
| alumno2@banco.test | Alumno | Ana Martínez López | 20260002 | Candidatura preseleccionada por decidir; un proyecto finalizado |
| alumno3@banco.test | Alumno | Bruno Hernández Ruiz | 20260003 | En el límite: 3 candidaturas vigentes |
| alumno4@banco.test | Alumno | Carla Gómez Torres | 20260004 | Asignada a un proyecto en curso |
| alumno5@banco.test | Alumno | Diego Ramírez Silva | 20260005 | Asignado a un proyecto; una candidatura rechazada |
| inactivo@banco.test | Alumno | Elena Castro Vega | 20260006 | Cuenta **inactiva** (no puede entrar) |
| profesor@banco.test | Profesor | Profesor de Prueba | PRF001 | 1 solicitud pendiente, 1 rechazada, 1 cancelada |
| profesor2@banco.test | Profesor | Mtra. Laura Ortiz Méndez | PRF002 | 1 solicitud pendiente; dirigió un proyecto finalizado |
| profesor3@banco.test | Profesor | Dr. Ricardo Navarro Paz | PRF003 | En el límite: dirige 3 proyectos activos |

## 3. Datos de partida

### Proyectos

| Folio | Estado | Título | Empresa | Cupo | Situación |
|---|---|---|---|---|---|
| PRY-00000001 | Borrador | Portal de citas para clínica veterinaria | empresa | 2 | Incompleto, listo para terminarlo y enviarlo |
| PRY-00000002 | En revisión | Sistema de inventario para almacén | empresa | 2 | Esperando decisión del administrador |
| PRY-00000003 | En revisión | Rastreo de unidades de reparto | empresa2 | 3 | Esperando decisión del administrador |
| PRY-00000004 | Devuelto | App de control de asistencia | empresa | 2 | Con observaciones del administrador |
| PRY-00000005 | Rechazado | Página de ventas por catálogo | empresa | 1 | Estado final |
| PRY-00000006 | Publicado | Plataforma de seguimiento de egresados | empresa | 3 | 2 candidaturas pendientes, 1 en revisión, 1 rechazada; 2 solicitudes de dirección pendientes; sin director |
| PRY-00000007 | Publicado | Tablero de indicadores de ventas | empresa2 | 1 | Director PRF003 y cupo lleno (alumno5); listo para iniciar; 1 candidatura en revisión sin cupo |
| PRY-00000008 | En curso | Asistente virtual de atención a clientes | empresa | 3 | Director PRF003, alumno4 asignada, 1 candidatura pendiente |
| PRY-00000009 | Publicado | Sistema de reservas de andenes | empresa2 | 3 | Director PRF003, sin alumnos |
| PRY-00000010 | Publicado | Digitalización de expedientes de clientes | empresa2 | 2 | Sin director, sin solicitudes |
| PRY-00000011 | Finalizado | Migración de la base de clientes | empresa | 2 | Historial: alumno2 y PRF002 con asignación finalizada |
| PRY-00000012 | Cancelado | Kiosco de autoservicio | empresa | 2 | Historial: solicitudes canceladas al cancelar el proyecto |

### Candidaturas

| Folio | Alumno | Proyecto | Estado |
|---|---|---|---|
| CAN-00000003 | alumno4 | PRY-00000008 | Aprobada, asignación activa |
| CAN-00000004 | alumno5 | PRY-00000007 | Aprobada, asignación activa |
| CAN-00000005 | alumno5 | PRY-00000006 | Rechazada (evaluada con 55.00) |
| CAN-00000006 | alumno2 | PRY-00000006 | **En revisión**, preseleccionada (92.65) |
| CAN-00000007 | alumno | PRY-00000006 | **Pendiente** |
| CAN-00000008 | alumno3 | PRY-00000006 | **Pendiente** |
| CAN-00000009 | alumno3 | PRY-00000007 | **En revisión**, pero el proyecto no tiene cupo |
| CAN-00000010 | alumno3 | PRY-00000008 | **Pendiente** |
| CAN-00000001 | alumno2 | PRY-00000011 | Aprobada, asignación finalizada |
| CAN-00000002, CAN-00000011 | alumno | PRY-00000012, PRY-00000009 | Canceladas |

### Solicitudes de dirección

| Folio | Profesor | Proyecto | Estado |
|---|---|---|---|
| SDI-00000007 | PRF001 | PRY-00000006 | **Pendiente** |
| SDI-00000008 | PRF002 | PRY-00000006 | **Pendiente** |
| SDI-00000003, 05, 06 | PRF003 | PRY-00000008, 07, 09 | Aprobadas, asignaciones activas |
| SDI-00000001 | PRF002 | PRY-00000011 | Aprobada, asignación finalizada |
| SDI-00000004 | PRF001 | PRY-00000008 | Rechazada |
| SDI-00000002 | PRF001 | PRY-00000012 | Cancelada (el proyecto se canceló) |

Los criterios de evaluación ya suman 100 % (Conocimientos técnicos 40, Habilidades blandas 25, Experiencia previa 20, Disponibilidad 15), así que la empresa puede evaluar desde el inicio. Cada cuenta tiene notificaciones; las de los últimos tres días aparecen sin leer. El historial de auditoría ya trae las operaciones que generaron estos datos.

## 4. Flujos

Cada flujo cambia los datos. Si quieres repetirlo, vuelve a correr `sembrar.php`.

### 4.1 Acceso y cuenta (todos los roles)

1. Entra con cualquier cuenta de la tabla: cada rol llega a su propio panel.
2. Entra con **inactivo@banco.test**: el sistema no la deja pasar.
3. En **Mi cuenta** (menú del usuario, `mod_comun/cuenta.php`) cambia la contraseña; sal y vuelve a entrar con la nueva.
4. Bloqueo por intentos: escribe mal la contraseña de **alumno5@banco.test** 5 veces seguidas; la cuenta queda bloqueada 15 minutos (vuelve a correr el script si no quieres esperar).
5. Intenta abrir una pantalla de otro rol (por ejemplo `mod_admin/` con la sesión de un alumno): debe aparecer la página de acceso denegado.

### 4.2 Registro de empresa

1. Sin sesión, abre la página de inicio y elige registrar empresa.
2. Llena el formulario con un correo nuevo y un RFC válido en mayúsculas (por ejemplo `NEM210101AB3`).
3. Al terminar, la empresa queda con su panel y un aviso de bienvenida, y **admin@banco.test** recibe una notificación del registro.
4. Prueba también con un RFC o correo ya existentes (`TDE200101AB1`, `empresa@banco.test`): debe rechazarlo.

### 4.3 Empresa: registrar y enviar un proyecto (empresa@banco.test)

1. **Mis proyectos** → abre **PRY-00000001** (borrador) → Editar.
2. Pulsa **Enviar a revisión** sin llenar todo: el formulario marca los campos obligatorios que faltan.
3. Completa los campos, agrega habilidades requeridas y envíalo. Queda **En revisión** y aparece en la bandeja del administrador.
4. Para crear uno desde cero usa **Nuevo proyecto** y guárdalo como borrador primero.

### 4.4 Empresa: corregir un proyecto devuelto (empresa@banco.test)

1. Abre **PRY-00000004** (Devuelto). Arriba se ven las observaciones del administrador.
2. Edita lo que pide (alcance y equipo para leer los QR) y reenvíalo a revisión.

### 4.5 Administrador: revisar proyectos (admin@banco.test)

1. **Proyectos por revisar** muestra PRY-00000002 y PRY-00000003 (y los que hayas enviado en 4.3 y 4.4).
2. Abre uno y prueba las tres decisiones, una por proyecto:
   - **Aprobar**: pasa a Publicado y ya lo ven alumnos y profesores.
   - **Devolver**: pide una justificación; vuelve a la empresa como Devuelto.
   - **Rechazar**: estado final.
3. La empresa dueña recibe la notificación de cada decisión (entra con su cuenta para verla).

### 4.6 Alumno: perfil y postulación (alumno@banco.test)

1. **Mis habilidades**: agrega o cambia el nivel de alguna habilidad. **Mi perfil**: edita carrera o datos académicos.
2. **Proyectos disponibles** → abre **PRY-00000010**. Se ve el porcentaje de coincidencia con tus habilidades.
3. Pulsa **Postularme**. La candidatura queda Pendiente y la empresa empresa2 recibe el aviso.
4. Vuelve a postularte a **PRY-00000009**: lo puedes hacer aunque ya tuviste una solicitud ahí, porque la anterior está cancelada.
5. **Mis solicitudes**: cancela la candidatura de **PRY-00000006**. La empresa recibe el aviso.

### 4.7 Alumno: reglas de postulación

- **alumno3@banco.test** → abre PRY-00000010 e intenta postularte: aparece «Ya tienes 3 candidaturas vigentes». Cancela una en **Mis solicitudes** y vuelve a intentarlo: ahora sí deja.
- **alumno5@banco.test** → abre PRY-00000006: su candidatura anterior está Rechazada, pero puede volver a postularse.
- **alumno2@banco.test** → abre PRY-00000006: ya tiene una solicitud vigente y no puede duplicarla.
- Los proyectos en borrador, revisión, rechazados, finalizados o cancelados no aparecen en **Proyectos disponibles**.

### 4.8 Empresa: evaluar candidatos (empresa@banco.test)

1. Abre **PRY-00000006** → **Candidatos**. Hay cuatro: Ana (en revisión, preseleccionada), Alumno de Prueba y Bruno (pendientes) y Diego (rechazado).
2. Abre a **Alumno de Prueba** → Evaluar. Se ve su perfil y la comparativa contra los requisitos.
3. Califica cada criterio de 0 a 100 (prueba un valor como 120 para ver el error), marca **Preseleccionar** y guarda.
4. La candidatura pasa a **En revisión**, el alumno recibe el aviso y el administrador recibe «Hay una candidatura por decidir».
5. Para ver una candidatura que ya está **en curso**, abre **PRY-00000008** → Candidatos y evalúa a Bruno.

### 4.9 Administrador: decidir candidaturas (admin@banco.test)

1. **Candidaturas por decidir**: aparecen CAN-00000006 (Ana, en PRY-00000006) y CAN-00000009 (Bruno, en PRY-00000007), más las que hayas evaluado en 4.8.
2. Abre **CAN-00000006** → Aprobar con una justificación de al menos 10 caracteres. Ana queda asignada; ella y la empresa reciben el aviso, y aparece en **Asignaciones**.
3. Abre **CAN-00000009** → Aprobar: el sistema no deja, porque PRY-00000007 tiene cupo 1 y ya está lleno. Recházala con justificación.
4. Prueba aprobar sin justificación para ver la validación.

### 4.10 Profesor: solicitar dirección (profesor@banco.test)

1. **Proyectos disponibles** → abre **PRY-00000010** → **Solicitar dirección**. Escribe experiencia y justificación y envía. El administrador recibe el aviso.
2. **Mis solicitudes de dirección**: ahí están la nueva, la pendiente de PRY-00000006, la rechazada de PRY-00000008 y la cancelada de PRY-00000012.
3. Abre **PRY-00000009**: no deja solicitar porque el proyecto ya tiene director.
4. Con **profesor3@banco.test** abre **PRY-00000006**: no deja solicitar porque ya dirige 3 proyectos activos. En **Proyectos que dirijo** se ven los tres.

### 4.11 Administrador: decidir solicitudes de dirección (admin@banco.test)

1. **Solicitudes de dirección**: hay dos para PRY-00000006 (PRF001 y PRF002).
2. Aprueba la de **PRF002** (SDI-00000008). La de PRF001 se rechaza sola porque el proyecto solo puede tener un director; los dos profesores y la empresa reciben su aviso.
3. Si en 4.10 creaste la solicitud para PRY-00000010, recházala con justificación.

### 4.12 Administrador: ciclo de vida del proyecto (admin@banco.test)

En **Proyectos** abre cada uno:

1. **PRY-00000007** (Publicado, con director y alumno): **Iniciar**. Pasa a En curso y avisa a la empresa, al alumno y al director.
2. **PRY-00000006**: Iniciar no procede mientras no tenga director y al menos un alumno asignado (hazlo antes de 4.9 y 4.11 para ver el mensaje; después de esos pasos ya se puede iniciar).
3. **PRY-00000008** (En curso): **Finalizar**. La asignación de Carla y la dirección de PRF003 quedan finalizadas, la candidatura pendiente de Bruno se cancela y él recibe el aviso de que ya puede usar ese lugar. Después de esto profesor3 vuelve a tener lugar para dirigir otro proyecto.
4. **PRY-00000009** (Publicado): **Cancelar**. Se cierra la dirección activa y se cancelan las solicitudes abiertas.
5. Los proyectos Finalizados, Cancelados y Rechazados ya no ofrecen acciones.

### 4.13 Administrador: usuarios y catálogos (admin@banco.test)

1. **Usuarios**: filtra por rol o estado; activa a **inactivo@banco.test** desde la lista y entra con ella para comprobarlo. Crea un profesor nuevo con contraseña propia. Intenta desactivar tu propia cuenta: no se permite.
2. **Habilidades**: agrega una habilidad nueva (aparece en el perfil del alumno y en el formulario de proyecto). Intenta borrar «PHP»: no se puede porque está en uso (solo se puede editar).
3. **Criterios**: cambia un peso para que la suma no dé 100; mientras tanto la empresa no puede evaluar. Regrésalo a 100.

### 4.14 Consultas (admin@banco.test)

1. **Asignaciones**: alumnos y directores activos, finalizados y cancelados.
2. **Reportes**: filtra por estado o empresa, descarga el CSV y prueba la versión para imprimir.
3. **Historial**: filtra por usuario u operación; aparecen las operaciones de los datos de prueba y las que hiciste.

### 4.15 Notificaciones (cualquier rol)

1. La campana muestra cuántos avisos sin leer hay. Cuentas con avisos sin leer al inicio: admin, empresa, empresa2, alumno2 y alumno3.
2. En **Notificaciones** márcalas como leídas una por una o todas a la vez, y usa el filtro de no leídas.
