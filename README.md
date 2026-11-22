# 📚 Proyecto Biblioteca

**Sistema de gestión de biblioteca**

Proyecto de desarrollo de un sistema web para la gestión de una biblioteca. Su objetivo es construir una plataforma que permita administrar y organizar los diferentes procesos relacionados con el funcionamiento de una biblioteca.

Actualmente, el proyecto se encuentra en su **Fase 1 de desarrollo**, enfocada principalmente en la implementación inicial del sistema de acceso de usuarios y sus mecanismos de seguridad.

---

## 🚧 Estado del proyecto

<kbd>Fase 1 — Desarrollo inicial</kbd>

El proyecto se encuentra en una etapa temprana de construcción. Se está trabajando en los componentes fundamentales que servirán como base para desarrollar posteriormente los módulos y funcionalidades del sistema de biblioteca.

### Funcionalidades implementadas

- [x] Formulario de inicio de sesión.
- [x] Registro de nuevos usuarios.
- [x] Validación de datos de registro.
- [x] Manejo de roles de usuario (alumno y profesor).
- [x] Validación de contraseñas.
- [x] Encriptación de contraseñas mediante hash.
- [x] Implementación de CAPTCHA.
- [x] Protección CSRF en formularios.
- [x] Control de intentos fallidos de inicio de sesión.
- [x] Bloqueo temporal de cuentas.
- [x] Manejo de sesiones de usuario.
- [x] Registro de eventos de seguridad.
- [x] Panel inicial después de iniciar sesión.
- [x] Cierre de sesión.

---

## 🛠️ Tecnologías utilizadas

| Tecnología | Uso |
|---|---|
| PHP | Lógica y funcionamiento del sistema. |
| HTML5 | Estructura de las páginas. |
| CSS3 | Diseño y estilos visuales. |
| JavaScript | Interacciones y funcionamiento del CAPTCHA. |
| MariaDB / MySQL | Gestión de la base de datos. |

---

## 📁 Estructura actual del proyecto

```text
Proyecto_Biblioteca/
│
├── captcha.php
├── captcha_modal.js
├── captcha_modal.php
├── config.php
├── estilos.css
├── login.php
├── logout.php
├── panel.php
├── registro.php
├── seguridad.php
└── verificar_captcha.php
```

*Esta es la estructura correspondiente a la primera fase. Se irá ampliando y reorganizando conforme avance el desarrollo.*

---

## ⚙️ Requisitos para ejecutar el proyecto

- PHP.
- Servidor web local (XAMPP o similar).
- MariaDB o MySQL.
- Navegador web.
- Editor de código (Visual Studio Code recomendado).

## 🚀 Instalación

1. Clonar o descargar el repositorio:

```bash
git clone https://github.com/Develenguas/Proyecto_Biblioteca.git
```

2. Colocar la carpeta del proyecto dentro del directorio de tu servidor local, por ejemplo, `htdocs` en XAMPP.

3. Configurar la conexión a la base de datos en el archivo correspondiente.

4. Crear o importar la base de datos necesaria.

5. Iniciar Apache y MariaDB/MySQL desde el entorno local.

6. Abrir el proyecto desde el navegador:

```text
http://localhost/Proyecto_Biblioteca/
```

*Nota: La configuración de la base de datos y los archivos necesarios para su inicialización pueden cambiar conforme avance el proyecto.*

---

## 🔮 Próximas etapas

El desarrollo continuará mediante la incorporación progresiva de nuevos módulos y funcionalidades.

Entre las funcionalidades contempladas para las siguientes etapas se encuentran:

- [ ] Desarrollo del módulo de administración.
- [ ] Gestión de libros.
- [ ] Registro y edición de libros.
- [ ] Consulta y búsqueda de libros.
- [ ] Gestión de usuarios.
- [ ] Control de préstamos.
- [ ] Control de devoluciones.
- [ ] Historial de préstamos.
- [ ] Gestión de disponibilidad de ejemplares.
- [ ] Mejoras en la interfaz y experiencia de usuario.
- [ ] Implementación de reportes.

Estas funcionalidades forman parte de la planificación y no representan características terminadas.

---

## 📌 Objetivo del desarrollo

Construir progresivamente un sistema web que facilite la administración de una biblioteca, centralizando la información y proporcionando herramientas para gestionar sus recursos y operaciones.

El proyecto se desarrollará por etapas, comenzando con los componentes fundamentales y avanzando hacia una solución integral.

---

## 👨‍💻 Desarrollo

**Repositorio:** [Proyecto_Biblioteca](https://github.com/Develenguas/Proyecto_Biblioteca)

**Estado:** En desarrollo — Fase 1.

