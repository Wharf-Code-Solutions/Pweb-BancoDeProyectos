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
* **Base de Datos:** MariaDB 10.11+ / MySQL 8.0+ (Motor InnoDB, Charset `utf8mb4`).
* **Frontend:** HTML5 semántico, CSS3 personalizado y Bootstrap 5 (incorporado de manera local).
* **Seguridad:**
  * Hasheo seguro de contraseñas (`BCRYPT`).
  * Prevención de Inyección SQL mediante sentencias preparadas.
  * Control de acceso estricto por propiedad (el usuario solo ve/edita lo que le pertenece según su sesión).
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

3. **Configuración de Credenciales:**
   * Copia `bd/config.ejemplo.php` como `bd/config.local.php` y ajusta los datos de conexión. Si no existe, se usa la plantilla (XAMPP: `root` sin contraseña).
   * *Aviso de seguridad:* Las credenciales reales de la base de datos **nunca** deben subirse a este repositorio.

4. **Ejecución:**
   * Coloca el proyecto en la carpeta raíz de tu servidor web (ej. `htdocs` en XAMPP).
   * Accede a través del navegador web: `http://localhost/<carpeta-del-proyecto>/` (por ejemplo `http://localhost/Banco_Proyectos/`). Para revisar la instalación abre `verificar.php` (solo responde con `debug` activo).

## 📜 Licencia y Derechos

Este es un proyecto académico de desarrollo de software. Todos los derechos reservados a los autores.
