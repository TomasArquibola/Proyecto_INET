-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 14-06-2025 a las 00:21:39
-- Versión del servidor: 10.4.24-MariaDB
-- Versión de PHP: 8.1.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Base de datos: `booking_app`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CrearReservaConServicios` (IN `p_ID_Usuario` INT, IN `p_ID_Paquete` INT, IN `p_Servicios` VARCHAR(255))   BEGIN
    DECLARE v_PrecioBase DECIMAL(10,2);
    DECLARE v_TotalServicios DECIMAL(10,2) DEFAULT 0;
    DECLARE v_TotalFinal DECIMAL(12,2);

    SELECT Precio_Base INTO v_PrecioBase
    FROM Paquete_Turistico
    WHERE ID_Paquete = p_ID_Paquete;

    IF p_Servicios IS NOT NULL AND p_Servicios != '' THEN
        SET @sql = CONCAT('SELECT SUM(Precio) INTO @suma FROM Servicio_Adicional WHERE ID_Servicio IN (', p_Servicios, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;

        SET v_TotalServicios = IFNULL(@suma, 0);
    END IF;

    SET v_TotalFinal = v_PrecioBase + v_TotalServicios;

    INSERT INTO Reserva (ID_Usuario, ID_Paquete, Fecha_Reserva, Total)
    VALUES (p_ID_Usuario, p_ID_Paquete, NOW(), v_TotalFinal);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paquete_turistico`
--

CREATE TABLE `paquete_turistico` (
  `ID_Paquete` int(11) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `Destino` varchar(100) NOT NULL,
  `Descripcion` text DEFAULT NULL,
  `Precio_Base` decimal(10,2) NOT NULL,
  `Disponible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `paquete_turistico`
--

INSERT INTO `paquete_turistico` (`ID_Paquete`, `Nombre`, `Destino`, `Descripcion`, `Precio_Base`, `Disponible`) VALUES
(1, 'Aventura en la Patagonia', 'El Calafate, Argentina', 'Explora los glaciares y la naturaleza indómita de la Patagonia argentina.', '850.00', 1),
(2, 'Relax en el Caribe', 'Cancún, México', 'Disfruta de playas de arena blanca y aguas cristalinas en el paraíso caribeño.', '1200.00', 1),
(3, 'Descubriendo Europa', 'París, Francia', 'Un viaje inolvidable por la ciudad de la luz y sus encantos.', '950.00', 1),
(4, 'Safari Africano', 'Serengeti, Tanzania', 'Vive la emoción de un safari en la majestuosa sabana africana.', '2500.00', 1),
(5, 'Cultura Andina', 'Cusco, Perú', 'Explora la antigua civilización Inca y la magia de Machu Picchu.', '700.00', 1),
(6, 'Islas Griegas', 'Santorini, Grecia', 'Disfruta de paisajes de ensueño y atardeceres inolvidables en el Mar Egeo.', '1100.00', 1),
(7, 'Naturaleza de Costa Rica', 'La Fortuna, Costa Rica', 'Aventura en la selva, volcanes y aguas termales en un paraíso ecológico.', '900.00', 1),
(8, 'Ciudades Imperiales de Marruecos', 'Marrakech, Marruecos', 'Un viaje a través de la historia y la cultura de las fascinantes ciudades imperiales.', '1050.00', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reserva`
--

CREATE TABLE `reserva` (
  `ID_Reserva` int(11) NOT NULL,
  `ID_Usuario` int(11) DEFAULT NULL,
  `ID_Paquete` int(11) DEFAULT NULL,
  `Fecha_Reserva` datetime NOT NULL,
  `Total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicio_adicional`
--

CREATE TABLE `servicio_adicional` (
  `ID_Servicio` int(11) NOT NULL,
  `ID_Paquete` int(11) DEFAULT NULL,
  `Tipo` enum('Traslado','Asistencia','Guía','Tour','Otros') NOT NULL,
  `Descripcion` text DEFAULT NULL,
  `Precio` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `servicio_adicional`
--

INSERT INTO `servicio_adicional` (`ID_Servicio`, `ID_Paquete`, `Tipo`, `Descripcion`, `Precio`) VALUES
(1, 1, 'Tour', 'Excursión al Glaciar Perito Moreno', '120.00'),
(2, 1, 'Traslado', 'Traslado aeropuerto-hotel-aeropuerto', '40.00'),
(3, 1, 'Asistencia', 'Asistencia al viajero premium', '50.00'),
(4, 2, 'Tour', 'Nado con delfines', '90.00'),
(5, 2, 'Traslado', 'Traslado aeropuerto-resort-aeropuerto', '60.00'),
(6, 2, 'Otros', 'Cena romántica en la playa', '80.00'),
(7, 3, 'Tour', 'Tour por la Torre Eiffel y Louvre', '75.00'),
(8, 3, 'Guía', 'Guía turístico bilingüe por un día', '150.00'),
(9, 3, 'Traslado', 'Traslado privado desde el aeropuerto', '100.00'),
(10, 4, 'Tour', 'Safari fotográfico de día completo', '300.00'),
(11, 4, 'Traslado', 'Vuelos internos entre campamentos', '200.00'),
(12, 4, 'Otros', 'Noche extra en campamento de lujo', '400.00'),
(13, 5, 'Tour', 'Excursión a Machu Picchu (tren incluido)', '250.00'),
(14, 5, 'Guía', 'Guía local para el Valle Sagrado', '80.00'),
(15, 5, 'Traslado', 'Traslado estación-hotel-estación', '30.00'),
(16, 6, 'Tour', 'Crucero al atardecer en caldera', '95.00'),
(17, 6, 'Traslado', 'Ferry inter-islas', '70.00'),
(18, 6, 'Otros', 'Clase de cocina griega', '60.00'),
(19, 7, 'Tour', 'Canopy y puentes colgantes', '85.00'),
(20, 7, 'Traslado', 'Traslado desde San José', '50.00'),
(21, 7, 'Otros', 'Entrada a termales Tabacón', '70.00'),
(22, 8, 'Tour', 'Visita guiada por la Medina', '60.00'),
(23, 8, 'Guía', 'Guía local para excursión al desierto', '120.00'),
(24, 8, 'Traslado', 'Traslado aeropuerto-riad-aeropuerto', '45.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `ID_Usuario` int(11) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `Contraseña` varchar(255) NOT NULL,
  `Fecha_Registro` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`ID_Usuario`, `Nombre`, `Email`, `Contraseña`, `Fecha_Registro`) VALUES
(3, 'tzaw', 'zawadskitiziano@gmail.com', '$2y$10$uNtu8FAmSpd22BUcfXHq4e8ZlIc9Pih1KzTc4/NfNP6n5vkVlTTdW', '2025-06-12');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `paquete_turistico`
--
ALTER TABLE `paquete_turistico`
  ADD PRIMARY KEY (`ID_Paquete`);

--
-- Indices de la tabla `reserva`
--
ALTER TABLE `reserva`
  ADD PRIMARY KEY (`ID_Reserva`),
  ADD KEY `ID_Usuario` (`ID_Usuario`),
  ADD KEY `ID_Paquete` (`ID_Paquete`);

--
-- Indices de la tabla `servicio_adicional`
--
ALTER TABLE `servicio_adicional`
  ADD PRIMARY KEY (`ID_Servicio`),
  ADD KEY `ID_Paquete` (`ID_Paquete`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`ID_Usuario`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `paquete_turistico`
--
ALTER TABLE `paquete_turistico`
  MODIFY `ID_Paquete` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `reserva`
--
ALTER TABLE `reserva`
  MODIFY `ID_Reserva` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servicio_adicional`
--
ALTER TABLE `servicio_adicional`
  MODIFY `ID_Servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `ID_Usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `reserva`
--
ALTER TABLE `reserva`
  ADD CONSTRAINT `reserva_ibfk_1` FOREIGN KEY (`ID_Usuario`) REFERENCES `usuario` (`ID_Usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `reserva_ibfk_2` FOREIGN KEY (`ID_Paquete`) REFERENCES `paquete_turistico` (`ID_Paquete`) ON DELETE CASCADE;

--
-- Filtros para la tabla `servicio_adicional`
--
ALTER TABLE `servicio_adicional`
  ADD CONSTRAINT `servicio_adicional_ibfk_1` FOREIGN KEY (`ID_Paquete`) REFERENCES `paquete_turistico` (`ID_Paquete`) ON DELETE CASCADE;
COMMIT;
