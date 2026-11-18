# Sistema de Gestión de Banco de Proyectos Académicos 🎓💻

Este proyecto es un sistema de información web desarrollado para gestionar un **Banco de Proyectos Académicos** del área de Sistemas Computacionales. Su objetivo es vincular las necesidades tecnológicas reales de las empresas con las competencias de los alumnos y la capacidad de dirección de los profesores, a través de un proceso controlado, transparente y trazable.

**Autores:**
* [Carlos Daniel Rodríguez Cano](https://github.com/Cardani13)
* [Evaristo Romero Tirado](https://github.com/EvaristoRT)

## 🎯 Descripción General

El sistema permite el registro, consulta, evaluación, autorización y asignación de proyectos académicos. Está diseñado para hacer cumplir estrictas reglas de negocio, como los límites de participación simultánea (máximo 3 candidaturas por alumno y 3 proyectos dirigidos por profesor) y flujos de autorización inmutables.

### Roles del Sistema

El sistema cuenta con control de acceso por propiedad y funciones específicas para 4 roles:

1. **🏢 Empresa:** Registra proyectos (necesidades tecnológicas), indica requisitos (habilidades duras y blandas), y evalúa/recomienda candidatos.
2. **🎓 Alumno:** Mantiene su perfil de habilidades, consulta proyectos disponibles y se postula a un máximo de 3 candidaturas vigentes simultáneamente.
3. **👨‍🏫 Profesor:** Consulta proyectos, solicita dirigirlos (basado en su experiencia) y administra hasta un máximo de 3 proyectos activos.
4. **⚙️ Administrador:** Gestiona catálogos, aprueba/rechaza publicaciones de proyectos, y emite las autorizaciones oficiales para formalizar las asignaciones de alumnos y profesores.

## 🛠️ Tecnologías y Arquitectura

El proyecto está construido utilizando una arquitectura modular clásica basada en el lado del servidor, optimizada para entornos de hosting compartido.

* **Backend:** PHP 8.x nativo (Estructurado/Modular).
* **Base de Datos:** MariaDB 10.4+ / MySQL 8.0+ (Motor InnoDB, Charset `utf8mb4`).
* **Frontend:** HTML5 semántico, CSS3 personalizado y Bootstrap 5 (incorporado de manera local).
* **Seguridad:**
  * Hasheo seguro de contraseñas (`BCRYPT`) y regeneración de la sesión al iniciar sesión o cambiar la contraseña.
  * Prevención de Inyección SQL mediante sentencias preparadas.
  * Control de acceso estricto por propiedad (el usuario solo ve/edita lo que le pertenece según su sesión).
  * Token CSRF en todos los formularios `POST`; cierre de sesión solo por `POST`.
  * Bloqueo de 15 minutos tras 5 intentos fallidos de inicio de sesión sobre la misma cuenta.
  * Cookie de sesión `HttpOnly` y `SameSite=Lax`; cierre por inactividad a las 2 horas; una cuenta desactivada pierde su sesión abierta.
  * Las carpetas `inc`, `bd`, `sql`, `menumain` e `inputs` no se pueden abrir desde el navegador (`.htaccess`).
  * Historial de auditoría inmutable de las operaciones importantes y de los accesos denegados.
* **Integridad de Datos:** Base de datos con 20 tablas, 25 llaves foráneas, 50 restricciones `CHECK`, triggers como segunda capa de validación de reglas de negocio y folios únicos generados sin `AUTO_INCREMENT`.

### Estructura de Carpetas

La estructura sigue un patrón modular enfocado en la simplicidad y separación de responsabilidades:

* `/bd` - Scripts de conexión a la base de datos.
* `/inc` - Núcleo común: arranque (`inicio.php`), sesión y acceso por rol, CSRF, avisos, auditoría y menú.
* `/css`, `/js`, `/img` - Recursos estáticos locales. `css/app.css` es la hoja de las pantallas; los ajustes nuevos van en `css/extras.css`.
* `/lib`, `/fonts` - Bootstrap 5.3.3, Bootstrap Icons 1.11.3 y Montserrat servidos en local.
* `/inputs` - Componentes y fragmentos reutilizables de formularios HTML/PHP.
* `/menumain` - Plantillas: pública (A) y de aplicación (B) con menú lateral y menú móvil.
* `/errores` - Páginas 403, 404 y 500.
* `/sql` - Script de la base y datos de prueba.
* `/mod_*` - Un módulo por rol: `mod_alumno`, `mod_empresa`, `mod_profesor`, `mod_admin` y `mod_comun` (cuenta y notificaciones).

Cada página empieza con `require_once __DIR__ . '/../inc/inicio.php';` y, si es privada, `$usuario = requiere_rol('alumno');`. Un rol distinto recibe 403 y el intento queda en el historial de auditoría.

## 🚀 Instalación y Configuración (Entorno de Desarrollo)

Para levantar este proyecto en un entorno local (como XAMPP o WAMP):

1. **Clonar el repositorio:**
   ```bash
   git clone https://github.com/Wharf-Code-Solutions/Pweb-BancoDeProyectos.git
   ```

2. **Base de Datos:**
   * Importa el archivo `sql/banco_proyectos.sql` en tu servidor MariaDB/MySQL local. Este script creará la base de datos `banco_proyectos` y toda su estructura (tablas, triggers, vistas y procedimientos). Probado también en MariaDB 10.4 de XAMPP.
   * Para desarrollo, importa después `sql/datos_prueba.sql`: crea un usuario por rol (`admin@`, `empresa@`, `alumno@` y `profesor@banco.test`, contraseña `Prueba2026!`), el catálogo de habilidades y los criterios de evaluación.
   * Para probar todos los flujos a mano, corre `C:\xampp\php\php.exe sql\sembrar.php --confirmar`: borra y vuelve a crear la base con 12 cuentas y con proyectos, candidaturas y solicitudes en todos los estados. Las credenciales y los pasos de cada flujo están en `pruebas/GUIA_PRUEBAS.md`.

3. **Configuración de Credenciales:**
   * Copia `bd/config.ejemplo.php` como `bd/config.local.php` y ajusta los datos de conexión. Si no existe, se usa la plantilla (XAMPP: `root` sin contraseña).
   * *Aviso de seguridad:* Las credenciales reales de la base de datos **nunca** deben subirse a este repositorio.

4. **Ejecución:**
   * Coloca el proyecto en la carpeta raíz de tu servidor web (ej. `htdocs` en XAMPP).
   * Accede a través del navegador web: `http://localhost/<carpeta-del-proyecto>/` (por ejemplo `http://localhost/Banco_Proyectos/`). Para revisar la instalación abre `verificar.php` (solo responde con `debug` activo).

## 🔄 Flujo principal

1. La **empresa** se registra, crea su proyecto (puede guardarlo como borrador) y lo envía a revisión.
2. El **administrador** lo aprueba y publica, lo devuelve con correcciones o lo rechaza.
3. Los **alumnos** ven los proyectos publicados con la coincidencia de sus habilidades y se postulan (máximo 3 candidaturas vigentes).
4. La **empresa** evalúa a cada candidato con los criterios ponderados del Tec y puede preseleccionarlo.
5. El **administrador** aprueba la candidatura y, en el mismo paso, asigna al alumno (respetando el cupo).
6. Los **profesores** solicitan dirigir proyectos; el administrador aprueba y asigna al director (máximo 3 proyectos activos por profesor, un director por proyecto).
7. Con director y al menos un alumno, el administrador inicia el proyecto y, al terminar, lo finaliza; las asignaciones se cierran solas.

Cada paso deja una autorización o un registro en el historial, y avisa por notificación a las personas involucradas. El administrador tiene reportes (con impresión y exportación a CSV) y la consulta del historial de auditoría.

## 🧪 Pruebas en local

* Usuarios de prueba (contraseña `Prueba2026!`): `admin@banco.test`, `empresa@banco.test`, `alumno@banco.test` y `profesor@banco.test`.
* Los alumnos, profesores y administradores no se registran solos: sus cuentas las crea el administrador en **Usuarios → Nuevo usuario**. Las empresas sí pueden registrarse desde la página de inicio.
* **Pruebas automáticas de integridad:** con XAMPP encendido, ejecuta desde la carpeta del proyecto
  ```bash
  C:\xampp\php\php.exe pruebas\flujo.php
  ```
  Crea una base temporal, recorre el sitio como cada rol con datos válidos e inválidos (permisos, CSRF, formularios, límites de 3, cupo, estados, triggers y restricciones) y al final la borra; la aplicación vuelve sola a su base normal. Opciones: `--conservar` (no borra la base temporal) y `--url=http://localhost/OtraCarpeta`.
* El historial de auditoría no se puede borrar (lo impide un trigger). Para pruebas que crean muchos datos conviene usar una base temporal: importa los dos scripts en otra base y cambia `db_name` en `bd/config.local.php`.

## ✅ Antes de publicar

* En `bd/config.local.php` pon `'debug' => false` (oculta los detalles de los errores y desactiva `verificar.php`).
* Usa un usuario de base de datos con contraseña y solo los permisos que la aplicación necesita.
* Sirve el sitio por HTTPS: la cookie de sesión se marca como segura automáticamente.
* No importes `sql/datos_prueba.sql` ni corras `sql/sembrar.php` en producción.

## 📜 Licencia y Derechos

Este es un proyecto académico de desarrollo de software. Todos los derechos reservados a los autores.
