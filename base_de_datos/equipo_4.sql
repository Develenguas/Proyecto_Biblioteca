-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 19-11-2026 a las 16:44:40
-- Versión del servidor: 10.11.16-MariaDB
-- Versión de PHP: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `equipo_4`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bitacora_eventos`
--

CREATE TABLE `bitacora_eventos` (
  `id_bitacora_evento` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED DEFAULT NULL,
  `evento` varchar(50) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  `direccion_ip` varbinary(16) DEFAULT NULL,
  `fecha_evento` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Disparadores `bitacora_eventos`
--
DELIMITER $$
CREATE TRIGGER `tr_bitacora_eventos_antes_actualizar` BEFORE UPDATE ON `bitacora_eventos` FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora no se puede modificar';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_bitacora_eventos_antes_eliminar` BEFORE DELETE ON `bitacora_eventos` FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La bitacora no se puede eliminar';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `libros`
--

CREATE TABLE `libros` (
  `id_libro` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `autor` varchar(150) NOT NULL,
  `isbn` varchar(13) DEFAULT NULL,
  `anio_publicacion` smallint(5) UNSIGNED DEFAULT NULL,
  `id_usuario_registro` int(10) UNSIGNED NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `libros`
--

INSERT INTO `libros` (`id_libro`, `titulo`, `autor`, `isbn`, `anio_publicacion`, `id_usuario_registro`, `fecha_registro`, `fecha_actualizacion`) VALUES
(1, 'Cien anios de soledad', 'Gabriel Garcia Marquez', '9780307474728', 1967, 1, '2026-11-17 10:00:29', '2026-11-17 10:00:29'),
(2, 'El laberinto de la soledad', 'Octavio Paz', '9786071600400', 1950, 1, '2026-11-17 10:00:29', '2026-11-17 10:00:29'),
(3, 'Pedro Paramo', 'Juan Rulfo', '9788437604947', 1955, 2, '2026-11-17 10:00:29', '2026-11-17 10:00:29'),
(4, 'Don Quijote de la Mancha', 'Miguel de Cervantes', NULL, 1605, 2, '2026-11-17 10:00:29', '2026-11-17 10:00:29'),
(5, 'Clean Code', 'Robert C. Martin', '9780132350884', 2008, 2, '2026-11-17 10:00:29', '2026-11-17 10:00:29'),
(6, 'The Web Application Hacker\'s Handbook', 'Dafydd Stuttard', '9781118026472', 2011, 1, '2026-11-17 10:00:29', '2026-11-17 10:00:29');

--
-- Disparadores `libros`
--
DELIMITER $$
CREATE TRIGGER `tr_libros_antes_actualizar` BEFORE UPDATE ON `libros` FOR EACH ROW BEGIN
  IF NEW.id_usuario_registro <> OLD.id_usuario_registro
     OR NEW.fecha_registro <> OLD.fecha_registro THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No se puede cambiar el registrante ni la fecha de alta';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_libros_antes_insertar` BEFORE INSERT ON `libros` FOR EACH ROW BEGIN
  IF EXISTS (SELECT 1
             FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = NEW.id_usuario_registro
               AND (u.activo = 0 OR r.nombre_rol NOT IN ('administrador', 'profesor'))) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El usuario no tiene permiso para registrar libros';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` tinyint(3) UNSIGNED NOT NULL,
  `nombre_rol` varchar(30) NOT NULL,
  `descripcion` varchar(120) NOT NULL
) ;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`, `descripcion`) VALUES
(1, 'administrador', 'Gestiona usuarios y libros'),
(2, 'profesor', 'Consulta libros y registra libros nuevos'),
(3, 'alumno', 'Consulta libros');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(254) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` tinyint(3) UNSIGNED NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `intentos_fallidos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `fecha_bloqueo_hasta` datetime DEFAULT NULL,
  `fecha_ultimo_acceso` datetime DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `correo`, `password_hash`, `id_rol`, `activo`, `intentos_fallidos`, `fecha_bloqueo_hasta`, `fecha_ultimo_acceso`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Administrador Demo', 'admin@biblioteca.test', '$2y$12$fCFuY28pVCJ7yvt2QoqvFOz3UxBzizqeI05bHgjeNLbCVUkFoiYyG', 1, 1, 0, NULL, NULL, '2026-11-17 10:00:29', '2026-11-17 10:00:29'),
(2, 'Profesor Demo', 'profesor@biblioteca.test', '$2y$12$bY0R1UJKmqSeJCcnlkkeFefZWMNnoce9HRMOMpy.p1OQ6qCuY8dS6', 2, 1, 0, NULL, NULL, '2026-11-17 10:00:29', '2026-11-17 10:00:29'),
(3, 'Alumno Demo', 'alumno@biblioteca.test', '$2y$12$y67HuItrS65g42UOOF0qa.PPeID/cPF.jT3BFKeBdB.9unzetzhQ6', 3, 1, 0, NULL, NULL, '2026-11-17 10:00:29', '2026-11-17 10:00:29');

--
-- Disparadores `usuarios`
--
DELIMITER $$
CREATE TRIGGER `tr_usuarios_antes_actualizar` BEFORE UPDATE ON `usuarios` FOR EACH ROW BEGIN
  IF OLD.activo = 1
     AND OLD.id_rol = (SELECT id_rol FROM roles WHERE nombre_rol = 'administrador')
     AND (NEW.activo = 0 OR NEW.id_rol <> OLD.id_rol) THEN
    IF NOT EXISTS (SELECT 1 FROM usuarios
                   WHERE id_rol = OLD.id_rol AND activo = 1
                     AND id_usuario <> OLD.id_usuario) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Debe existir al menos un administrador activo';
    END IF;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_usuarios_antes_eliminar` BEFORE DELETE ON `usuarios` FOR EACH ROW BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Los usuarios no se eliminan; use activo = 0';
END
$$
DELIMITER ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bitacora_eventos`
--
ALTER TABLE `bitacora_eventos`
  ADD PRIMARY KEY (`id_bitacora_evento`),
  ADD KEY `idx_bitacora_eventos_id_usuario` (`id_usuario`),
  ADD KEY `idx_bitacora_eventos_fecha_evento` (`fecha_evento`);

--
-- Indices de la tabla `libros`
--
ALTER TABLE `libros`
  ADD PRIMARY KEY (`id_libro`),
  ADD UNIQUE KEY `uq_libros_isbn` (`isbn`),
  ADD KEY `idx_libros_titulo` (`titulo`),
  ADD KEY `idx_libros_autor` (`autor`),
  ADD KEY `idx_libros_id_usuario_registro` (`id_usuario_registro`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `uq_roles_nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuarios_correo` (`correo`),
  ADD KEY `idx_usuarios_id_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `bitacora_eventos`
--
ALTER TABLE `bitacora_eventos`
  MODIFY `id_bitacora_evento` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `libros`
--
ALTER TABLE `libros`
  MODIFY `id_libro` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `bitacora_eventos`
--
ALTER TABLE `bitacora_eventos`
  ADD CONSTRAINT `fk_bitacora_eventos_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `libros`
--
ALTER TABLE `libros`
  ADD CONSTRAINT `fk_libros_usuarios` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
