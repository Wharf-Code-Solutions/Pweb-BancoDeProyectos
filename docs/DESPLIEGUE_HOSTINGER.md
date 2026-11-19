# Despliegue en Hostinger · Banco de Proyectos

Guía para publicar el sistema en Hostinger (FTP + phpMyAdmin). Hay dos formas de hacerlo: **con el script** (opción A) o **a mano** (opción B). Las dos dejan el sitio igual.

> Este documento no tiene credenciales. Los datos de acceso los da el profesor o hPanel, y se guardan solo en la carpeta `privado\` de tu PC (ver sección 2).

## 1. Qué necesitas antes de empezar

| Dato | Para qué sirve | Dónde se usa |
|---|---|---|
| Host, usuario y contraseña del **FTP** (puerto 21) | Subir los archivos | `privado\ftp.env` o FileZilla |
| **Nombre de la base**, **usuario** y **contraseña** de MySQL | Que el sitio se conecte a la base | `bd/config.local.php` en el servidor |
| Enlace a **phpMyAdmin** | Crear las tablas y cargar los datos | Navegador |
| **URL del sitio** (dominio, subdominio o carpeta) | Probar el sitio publicado | Navegador |

- La URL del sitio **no** es la IP del FTP. Si abres la IP directo, Hostinger muestra un **403 Forbidden** genérico: es normal, ahí viven muchos sitios.
- El sitio funciona igual en la raíz de un dominio o dentro de una carpeta: detecta solo su ruta.
- En el servidor ya debe existir la base vacía (la crea el profesor o hPanel). **No** se crea desde el script SQL.

## 2. Qué se sube y qué no

Esta lista vale para el script y para FileZilla.

| Se sube (sitio) | No se sube nunca |
|---|---|
| `index.php`, `login.php`, `logout.php`, `registro_empresa.php` | `.git/`, `.gitignore`, `.gitattributes` |
| `.htaccess` de la raíz | `Pantallas/` (maquetas) |
| `bd/conexion.php` y `bd/.htaccess` | `bd/config.local.php` (tu config de XAMPP: root sin contraseña y debug activo) |
| `css/`, `js/`, `img/`, `fonts/` | `bd/config.ejemplo.php` |
| `vendor/` (Bootstrap e íconos) | `sql/` (los .sql se importan en phpMyAdmin, no se suben) |
| `inc/`, `inputs/`, `menumain/`, `errores/` | `pruebas/`, `pruebas.php`, `verificar.php` |
| `mod_admin/`, `mod_alumno/`, `mod_comun/`, `mod_empresa/`, `mod_profesor/` | `deploy/`, `docs/`, `privado/`, `README.md`, cualquier `.md` o `.docx` |

- Los **`.htaccess`** son importantes: bloquean el acceso desde el navegador a `bd/`, `inc/`, `inputs/` y `menumain/`. En FileZilla están ocultos: actívalos en **Servidor → Forzar mostrar archivos ocultos**.
- La carpeta de Bootstrap se llama **`vendor`** y no `lib` porque Hostinger no permite crear una carpeta llamada `lib` (responde *550 Permission denied*).
- En el servidor, `bd/config.local.php` se crea **una sola vez** con los datos de producción (paso A3 o B3).

## 3. Opción A: con el script

Todo se corre en una terminal (CMD o PowerShell) abierta en la carpeta del proyecto, por ejemplo `C:\xampp\htdocs\Banco_Proyectos`. El script es `deploy\subir.bat` (que llama a `deploy\subir_ftp.ps1`) y **siempre pide escribir `SI` antes de subir algo**.

### A0. Preparar la carpeta `privado\` (una sola vez por PC)

```
deploy\subir.bat -Iniciar
```

Crea la carpeta `privado\` dentro del proyecto. Git la ignora y el script nunca la sube. Si un archivo ya existe, no lo toca.

| Archivo | Contenido | Qué llenas tú |
|---|---|---|
| `privado\.htaccess` | Bloquea la carpeta en tu XAMPP | Nada |
| `privado\ftp.env` | `FTP_HOST=`, `FTP_USER=`, `FTP_PASS=`, `FTP_PORT=21` | Los datos del FTP |
| `privado\config_produccion.php` | La configuración que usará el sitio en el servidor | `db_name`, `db_user`, `db_pass`; deja `db_host` en `localhost` y `debug` en `false` |

Opcionales en `ftp.env`: `FTP_DIR=` (subcarpeta del servidor; vacío = donde entra el usuario FTP) y `FTP_TLS=1` (FTP cifrado, si el servidor lo acepta).

### A1. Generar los .sql para phpMyAdmin

```
deploy\subir.bat -PrepararSql
```

Pregunta si quieres cargar los **usuarios de prueba**. Deja los archivos en `privado\sql\`:

| Archivo | Qué contiene | Cuándo se usa |
|---|---|---|
| `0_borrar_todo.sql` | Borra las 20 tablas, las 4 vistas y el procedimiento de folios (los triggers se van con sus tablas) | Solo para empezar de cero |
| `1_estructura.sql` | `sql/banco_proyectos.sql` **sin** `CREATE DATABASE` / `USE` (en Hostinger fallarían por permisos): tablas, folios, procedimiento, triggers de reglas y vistas | Siempre, primero |
| `2_datos_prueba.sql` (si contestas `s`) | Copia de `sql/datos_prueba.sql`: 4 cuentas de prueba, 10 habilidades, 4 criterios que suman 100 % | Sitio de revisión |
| `2_datos_iniciales.sql` (si contestas `n`) | Un administrador real (pide nombre, correo, número de empleado, cargo y contraseña; el hash se calcula con el PHP de XAMPP) + los mismos catálogos | Sitio real |

Cuentas de prueba (todas con contraseña `Prueba2026!`): `admin@banco.test`, `empresa@banco.test`, `alumno@banco.test`, `profesor@banco.test`.

### A2. Importar en phpMyAdmin

1. Entra a phpMyAdmin y **haz clic en tu base** en la columna izquierda (si no está seleccionada, la importación falla).
2. Pestaña **Importar** → elige `privado\sql\1_estructura.sql` → **Importar**.
3. Repite con `privado\sql\2_datos_prueba.sql` (o `2_datos_iniciales.sql`).
4. Comprueba: 20 tablas y 4 vistas en la columna izquierda; la tabla `usuario` con 4 filas (o 1 con datos iniciales).

El nombre del archivo no importa: las tablas se crean en la base que tengas seleccionada.

### A3. Subir la configuración de producción

```
deploy\subir.bat -SubirConfig
```

1. Lee `privado\ftp.env` y muestra servidor y usuario (nunca la contraseña).
2. Pregunta al servidor si ya existe `bd/config.local.php` (solo consulta el tamaño).
3. Si **ya existe**, avisa y no toca nada: nunca sobrescribe una configuración.
4. Si **no existe**, pide `SI` y sube `privado\config_produccion.php` como `bd/config.local.php` (crea `bd/` si hace falta).

`bd/conexion.php` busca `bd/config.local.php`: en tu XAMPP usa el tuyo y en el servidor este, sin pisarse.

### A4. Subir los archivos del sitio

```
deploy\subir.bat -Simular
deploy\subir.bat -Todo
```

- `-Simular` solo muestra la lista (sección 2); no sube nada. Revísala.
- `-Todo` sube todos los archivos del sitio, uno por uno (`[1/90] ...`), creando carpetas cuando falten. Al final: *Listo: N archivo(s) subidos.*
- Guarda en `privado\estado_ftp.json` la huella (SHA-256) de cada archivo subido. Si se corta a la mitad, lo ya subido queda registrado y `deploy\subir.bat` sigue con lo que faltó.
- Abre una conexión por archivo: la primera subida es lenta, pero evita errores con carpetas anidadas.
- Si hay cambios sin commit en archivos del sitio, lo avisa en amarillo: se sube lo que está en disco.

### A5. Probar

1. Abre la URL del sitio y entra con `admin@banco.test` / `Prueba2026!`.
2. Prueba las otras tres cuentas y registra una empresa nueva desde el inicio.
3. Si sale una página de error al entrar, revisa la sección 7.

### Después de cada cambio

```
deploy\subir.bat
```

Compara las huellas y sube **solo los archivos que cambiaron**. Si borraste un archivo en local, lo lista; con `-Borrar` también lo borra del servidor (solo los que subió el script).

### Referencia rápida de comandos

| Comando | Qué hace | ¿Toca el servidor? |
|---|---|---|
| `deploy\subir.bat -Iniciar` | Crea `privado\` y sus plantillas | No |
| `deploy\subir.bat -PrepararSql` | Genera los .sql en `privado\sql\` | No |
| `deploy\subir.bat -SubirConfig` | Sube la config solo si no existe | Sí (1 archivo) |
| `deploy\subir.bat -Simular` | Muestra qué se subiría (compara con el registro local) | No |
| `deploy\subir.bat -Todo` | Sube todo el sitio | Sí |
| `deploy\subir.bat` | Sube solo lo que cambió | Sí |
| `deploy\subir.bat -Borrar` | Además borra del servidor lo que ya no existe en local | Sí |

## 4. Opción B: a mano (phpMyAdmin + FileZilla)

### B1. Base de datos

1. En phpMyAdmin haz clic en tu base (columna izquierda) → pestaña **SQL**.
2. Pega el contenido de `sql/banco_proyectos.sql`, **pero antes borra estas líneas** (cerca de la línea 38):
   ```
   CREATE DATABASE IF NOT EXISTS banco_proyectos
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;

   USE banco_proyectos;
   ```
3. **Continuar**. Luego pega `sql/datos_prueba.sql` completo → **Continuar**.

### B2. Archivos con FileZilla

1. Conéctate con el host, usuario y contraseña del FTP, puerto `21`. En el panel derecho, `/` es la raíz del sitio.
2. Activa **Servidor → Forzar mostrar archivos ocultos** (para ver los `.htaccess`).
3. Arrastra del panel izquierdo al derecho **solo** lo de la columna "Se sube" de la sección 2.
4. Revisa que en el servidor, dentro de `bd/`, solo estén `conexion.php` y `.htaccess`.

Al arrastrar a mano el script no se entera: la próxima vez que lo corras, volverá a subir esos archivos una vez (sin daño).

### B3. Configuración de la base

En el servidor crea `bd/config.local.php` (FileZilla: clic derecho en la lista de archivos → **Crear nuevo archivo**; o en hPanel → Administrador de archivos) con tus datos:

```php
<?php
return [
    'db_host'     => 'localhost',
    'db_name'     => 'NOMBRE_DE_TU_BASE',
    'db_user'     => 'USUARIO_DE_TU_BASE',
    'db_pass'     => 'CONTRASEÑA',
    'db_timezone' => '-06:00',
    'debug'       => false,
];
```

### B4. Probar

Igual que A5.

## 5. Empezar de cero

- **Base:** con el script, importa `0_borrar_todo.sql` y vuelve a importar `1_estructura.sql` y `2_...sql`. A mano: en phpMyAdmin selecciona todas las tablas y vistas → **Eliminar**; en **Rutinas** borra `sp_siguiente_folio`; vuelve a pegar los dos códigos. **Se pierde todo lo capturado en el sitio.**
- **Archivos:** `deploy\subir.bat -Todo` vuelve a subir todo encima. Para dejar el servidor vacío: bórralos en FileZilla o hPanel, borra `privado\estado_ftp.json` y repite A3 y A4.

## 6. Pasar de "revisión" a "real"

Las cuentas de prueba tienen una contraseña conocida (está en este documento y en el README). Cuando el sitio deje de ser de revisión: empieza de cero en la base y en `-PrepararSql` contesta `n` para crear un administrador real. Las demás cuentas se crean desde la app (**Usuarios → Nuevo usuario**); las empresas se registran solas.

## 7. Problemas conocidos

| Síntoma | Causa | Qué hacer |
|---|---|---|
| `403 Forbidden` al abrir la IP | La IP es del servidor compartido, no de tu sitio | Usa la URL del sitio |
| Página de error al abrir el sitio | `db_host`, nombre, usuario o contraseña de la base incorrectos | Edita `bd/config.local.php` en el servidor (FileZilla: clic derecho → Ver/Editar). Prueba como `db_host` el host de base de datos que te dieron. Copia el cambio a `privado\config_produccion.php` |
| `550 lib: Permission denied` | Hostinger no deja crear `lib` en la raíz | Ya resuelto: la carpeta es `vendor` |
| `550 ... Archivo no disponible` al subir | La carpeta no existía en el servidor | El script la crea y reintenta; si sigue, la línea amarilla dice qué respondió el servidor |
| Error de permisos al importar `banco_proyectos.sql` | Quedaron las líneas `CREATE DATABASE` / `USE` | Usa `1_estructura.sql` o bórralas (B1) |
| El sitio se ve sin estilos | Falta `vendor/` o `css/` en el servidor | Corre `deploy\subir.bat` o arrastra esas carpetas |
| El script dice "Nada que subir" pero falta algo | Lo subiste a mano o el registro no coincide | `deploy\subir.bat -Todo` |
| "No encuentro privado\ftp.env" | Falta el paso A0 | `deploy\subir.bat -Iniciar` |

## 8. Seguridad

- Las credenciales viven solo en `privado\` (ignorada por Git, nunca se sube) y en `bd/config.local.php` del servidor (bloqueado por `bd/.htaccess`).
- En el servidor `debug` siempre en `false`: los errores muestran una página genérica, sin detalles internos, y `verificar.php` no responde.
- Nunca subas `sql/`: además de no hacer falta, expone la estructura y los datos de prueba.
- `sql/sembrar.php` (12 cuentas y escenarios completos) es solo para XAMPP: borra y vuelve a crear la base, y en Hostinger no se puede.
