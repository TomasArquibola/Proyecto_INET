// server.js - Servidor Node.js con Express
const express = require('express');
const mysql = require('mysql2/promise');
const cors = require('cors');
const bodyParser = require('body-parser');
const PDFDocument = require('pdfkit'); // Para generar PDFs
const nodemailer = require('nodemailer'); // Para simular envío de emails

const app = express();
const PORT = 3000;

// Middleware
app.use(cors());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Configuración de la base de datos
const dbConfig = {
    host: 'localhost',
    user: 'root',              // <<-- CAMBIA POR TU USUARIO DE MySQL
    password: '',              // <<-- CAMBIA POR TU CONTRASEÑA DE MySQL
    database: 'booking_app',   // <<-- CAMBIA POR EL NOMBRE DE TU BASE DE DATOS
    charset: 'utf8mb4'
};

// Configuración de Nodemailer para simulación (NO USAR EN PRODUCCIÓN SIN CREDENCIALES REALES)
const transporter = nodemailer.createTransport({
    host: "smtp.ethereal.email", // Ejemplo de host para prueba, no real
    port: 587,
    secure: false, // true for 465, false for other ports
    auth: {
        user: "ejemplo@ethereal.email", // Generado por Ethereal Mail
        pass: "contraseñaGenerada"    // Generado por Ethereal Mail
    }
});

// Conexión a la base de datos
let db;

async function conectarDB() {
    try {
        db = await mysql.createConnection(dbConfig);
        console.log('Conectado a la base de datos MySQL');
    } catch (error) {
        console.error('Error al conectar a la base de datos:', error);
        process.exit(1);
    }
}

// --- Rutas de la API (Las rutas GET son las mismas que antes) ---

// GET /api/paquetes - Obtener todos los paquetes
app.get('/api/paquetes', async (req, res) => {
    try {
        const [paquetes] = await db.execute(`
            SELECT p.ID_Paquete, p.Nombre, p.Destino, p.Descripcion, p.Precio_Base, p.Disponible,
                   GROUP_CONCAT(
                       CONCAT(s.ID_Servicio, ':', s.Tipo, ':', s.Descripcion, ':', s.Precio)
                       ORDER BY s.ID_Servicio SEPARATOR '|'
                   ) as servicios
            FROM paquete_turistico p
            LEFT JOIN servicio_adicional s ON p.ID_Paquete = s.ID_Paquete
            WHERE p.Disponible = 1
            GROUP BY p.ID_Paquete
            ORDER BY p.ID_Paquete
        `);

        const paquetesConServicios = paquetes.map(paquete => {
            const serviciosAdicionales = [];
            if (paquete.servicios) {
                paquete.servicios.split('|').forEach(servicioStr => {
                    const [id, tipo, descripcion, precio] = servicioStr.split(':');
                    if (id && tipo && descripcion && precio) {
                        serviciosAdicionales.push({
                            id: parseInt(id), tipo, descripcion, precio: parseFloat(precio)
                        });
                    }
                });
            }
            return {
                id: paquete.ID_Paquete, nombre: paquete.Nombre, destino: paquete.Destino,
                descripcion: paquete.Descripcion, precio_base: parseFloat(paquete.Precio_Base),
                disponible: paquete.Disponible === 1, servicios_adicionales: serviciosAdicionales
            };
        });
        res.json(paquetesConServicios);
    } catch (error) {
        console.error('Error al obtener paquetes:', error);
        res.status(500).json({ error: 'Error interno del servidor al obtener paquetes' });
    }
});

// GET /api/paquete/:id - Obtener un paquete específico
app.get('/api/paquete/:id', async (req, res) => {
    try {
        const { id } = req.params;
        const [paquetes] = await db.execute(
            'SELECT ID_Paquete, Nombre, Destino, Descripcion, Precio_Base, Disponible FROM paquete_turistico WHERE ID_Paquete = ? AND Disponible = 1',
            [id]
        );
        if (paquetes.length === 0) {
            return res.status(404).json({ error: 'Paquete no encontrado o no disponible' });
        }
        const paquete = paquetes[0];
        const [servicios] = await db.execute(
            'SELECT ID_Servicio, Tipo, Descripcion, Precio FROM servicio_adicional WHERE ID_Paquete = ?',
            [id]
        );
        const serviciosAdicionales = servicios.map(servicio => ({
            id: servicio.ID_Servicio, tipo: servicio.Tipo, descripcion: servicio.Descripcion, precio: parseFloat(servicio.Precio)
        }));
        const resultado = {
            id: paquete.ID_Paquete, nombre: paquete.Nombre, destino: paquete.Destino,
            descripcion: paquete.Descripcion, precio_base: parseFloat(paquete.Precio_Base),
            disponible: paquete.Disponible === 1, servicios_adicionales: serviciosAdicionales
        };
        res.json(resultado);
    } catch (error) {
        console.error('Error al obtener paquete específico:', error);
        res.status(500).json({ error: 'Error interno del servidor al obtener paquete' });
    }
});

// GET /api/buscar?q=query - Buscar paquetes
app.get('/api/buscar', async (req, res) => {
    try {
        const { q } = req.query;
        if (!q || q.trim() === '') {
            return res.status(400).json({ error: 'Parámetro de búsqueda "q" requerido' });
        }
        const searchTerm = `%${q}%`;
        const [paquetes] = await db.execute(`
            SELECT p.ID_Paquete, p.Nombre, p.Destino, p.Descripcion, p.Precio_Base, p.Disponible,
                   GROUP_CONCAT(
                       CONCAT(s.ID_Servicio, ':', s.Tipo, ':', s.Descripcion, ':', s.Precio)
                       ORDER BY s.ID_Servicio SEPARATOR '|'
                   ) as servicios
            FROM paquete_turistico p
            LEFT JOIN servicio_adicional s ON p.ID_Paquete = s.ID_Paquete
            WHERE p.Disponible = 1 AND (p.Nombre LIKE ? OR p.Destino LIKE ? OR p.Descripcion LIKE ?)
            GROUP BY p.ID_Paquete ORDER BY p.ID_Paquete
        `, [searchTerm, searchTerm, searchTerm]);
        const paquetesConServicios = paquetes.map(paquete => {
            const serviciosAdicionales = [];
            if (paquete.servicios) {
                paquete.servicios.split('|').forEach(servicioStr => {
                    const [id, tipo, descripcion, precio] = servicioStr.split(':');
                    if (id && tipo && descripcion && precio) {
                        serviciosAdicionales.push({
                            id: parseInt(id), tipo, descripcion, precio: parseFloat(precio)
                        });
                    }
                });
            }
            return {
                id: paquete.ID_Paquete, nombre: paquete.Nombre, destino: paquete.Destino,
                descripcion: paquete.Descripcion, precio_base: parseFloat(paquete.Precio_Base),
                disponible: paquete.Disponible === 1, servicios_adicionales: serviciosAdicionales
            };
        });
        res.json(paquetesConServicios);
    } catch (error) {
        console.error('Error al buscar paquetes:', error);
        res.status(500).json({ error: 'Error interno del servidor al buscar paquetes' });
    }
});

// POST /api/reservar - Crear una reserva
app.post('/api/reservar', async (req, res) => {
    try {
        const { id_usuario, id_paquete, servicios_adicionales = [], datos_comprador } = req.body;

        if (!id_usuario || !id_paquete || !datos_comprador || !datos_comprador.nombre) {
            return res.status(400).json({ success: false, message: 'Datos incompletos para la reserva: ID de usuario, ID de paquete y nombre del comprador son requeridos.' });
        }

        const serviciosStr = servicios_adicionales.join(',');

        // 1. Llamar al procedimiento almacenado para crear la reserva en la DB
        const [result] = await db.execute(
            'CALL CrearReservaConServicios(?, ?, ?)',
            [id_usuario, id_paquete, serviciosStr]
        );

        // 2. Obtener los detalles completos del paquete y servicios para la factura (si no se enviaron completos desde el frontend)
        const [paqueteRows] = await db.execute('SELECT Nombre, Precio_Base FROM paquete_turistico WHERE ID_Paquete = ?', [id_paquete]);
        if (paqueteRows.length === 0) {
            return res.status(404).json({ success: false, message: 'Paquete no encontrado para generar factura.' });
        }
        const paqueteDetalles = paqueteRows[0];

        let serviciosFactura = [];
        let totalServiciosAdicionales = 0;
        if (servicios_adicionales.length > 0) {
            const [serviciosRows] = await db.execute(`SELECT Tipo, Descripcion, Precio FROM servicio_adicional WHERE ID_Servicio IN (${servicios_adicionales.join(',')})`);
            serviciosFactura = serviciosRows.map(s => ({
                tipo: s.Tipo,
                descripcion: s.Descripcion,
                precio: parseFloat(s.Precio)
            }));
            totalServiciosAdicionales = serviciosFactura.reduce((sum, s) => sum + s.precio, 0);
        }

        const totalFinal = parseFloat(paqueteDetalles.Precio_Base) + totalServiciosAdicionales;

        // 3. Generar el PDF de la factura
        const doc = new PDFDocument();
        const buffers = [];
        doc.on('data', buffers.push.bind(buffers));
        doc.on('end', async () => {
            const pdfBuffer = Buffer.concat(buffers);
            
            // Simular envío de email (o enviarlo de verdad con credenciales configuradas)
            try {
                // Comentar o eliminar si no tienes un transporter real configurado
                /*
                const info = await transporter.sendMail({
                    from: '"Agencia de Viajes" <no-reply@agencia.com>',
                    to: datos_comprador.email || "test@example.com", // Usar email del comprador si existe, sino uno de prueba
                    subject: `Factura de Reserva - ${paqueteDetalles.Nombre}`,
                    html: `
                        <p>Estimado/a ${datos_comprador.nombre},</p>
                        <p>Gracias por tu reserva con nosotros. Adjuntamos la factura de tu paquete turístico.</p>
                        <p>Atentamente,<br/>Tu Agencia de Viajes</p>
                    `,
                    attachments: [{
                        filename: `factura_reserva_${new Date().getTime()}.pdf`,
                        content: pdfBuffer,
                        contentType: 'application/pdf'
                    }]
                });
                console.log("Mensaje enviado: %s", info.messageId);
                console.log("URL de previsualización (si Ethereal): %s", nodemailer.getTestMessageUrl(info));
                */
               console.log(`Simulando envío de factura a ${datos_comprador.email || 'correo de prueba'}. PDF generado.`);

            } catch (emailError) {
                console.error("Error al enviar el email de factura:", emailError);
                // No detenemos la respuesta al cliente por un error de email, pero lo registramos
            }

            // 4. Enviar la respuesta al frontend (incluyendo el PDF para descarga)
            res.status(201).json({
                success: true,
                message: 'Reserva creada con éxito y factura generada.',
                id_reserva_creada: result.insertId,
                total: totalFinal.toFixed(2), // Enviamos el total calculado
                pdf: pdfBuffer.toString('base64') // Enviar el PDF como base64 para el frontend
            });
        });

        // --- Contenido de la Factura PDF ---
        const invoiceNumber = `INV-${Math.floor(Math.random() * 1000000)}`; // Número de factura simulado
        const emissionDate = new Date().toLocaleDateString('es-AR'); // Fecha actual

        doc.fontSize(24).text('FACTURA', { align: 'center' }).moveDown();

        // Datos del emisor
        doc.fontSize(12).text('Datos del Emisor:', { underline: true }).moveDown(0.5);
        doc.text('Agencia de Viajes Maravilla', { indent: 20 });
        doc.text('NIF/CIF: B12345678', { indent: 20 });
        doc.text('Dirección: Av. Ficticia 123, CABA, Argentina', { indent: 20 }).moveDown();

        // Número de factura y fecha
        doc.fontSize(12).text(`Número de Factura: ${invoiceNumber}`, { align: 'right' });
        doc.text(`Fecha de Emisión: ${emissionDate}`, { align: 'right' }).moveDown();

        // Datos del receptor
        doc.fontSize(12).text('Datos del Receptor:', { underline: true }).moveDown(0.5);
        doc.text(`Nombre: ${datos_comprador.nombre}`, { indent: 20 });
        if (datos_comprador.email) doc.text(`Email: ${datos_comprador.email}`, { indent: 20 });
        if (datos_comprador.direccion) doc.text(`Dirección: ${datos_comprador.direccion}`, { indent: 20 });
        doc.moveDown();

        // Descripción de productos/servicios
        doc.fontSize(12).text('Detalle de la Reserva:', { underline: true }).moveDown(0.5);
        
        // Encabezados de tabla
        const tableTop = doc.y;
        const itemX = 50;
        const qtyX = 300;
        const priceX = 400;
        const totalX = 500;

        doc.font('Helvetica-Bold').text('Descripción', itemX, tableTop)
           .text('Cantidad', qtyX, tableTop)
           .text('Precio Unitario', priceX, tableTop)
           .text('Total', totalX, tableTop);
        doc.moveTo(itemX - 5, doc.y + 10).lineTo(totalX + 50, doc.y + 10).stroke(); // Línea debajo de encabezados
        doc.moveDown(0.5);

        let yPosition = doc.y;

        // Paquete principal
        doc.font('Helvetica').text(`${paqueteDetalles.Nombre} (${paqueteDetalles.Destino})`, itemX, yPosition)
           .text('1', qtyX, yPosition)
           .text(`$${paqueteDetalles.Precio_Base.toFixed(2).replace('.', ',')}`, priceX, yPosition)
           .text(`$${paqueteDetalles.Precio_Base.toFixed(2).replace('.', ',')}`, totalX, yPosition);
        yPosition += 20;

        // Servicios adicionales
        serviciosFactura.forEach(service => {
            doc.text(`- ${service.tipo}: ${service.descripcion}`, itemX + 10, yPosition)
               .text('1', qtyX, yPosition)
               .text(`$${service.precio.toFixed(2).replace('.', ',')}`, priceX, yPosition)
               .text(`$${service.precio.toFixed(2).replace('.', ',')}`, totalX, yPosition);
            yPosition += 20;
        });

        doc.moveDown();
        doc.moveTo(itemX - 5, doc.y).lineTo(totalX + 50, doc.y).stroke(); // Línea divisoria
        doc.moveDown(0.5);

        // Totales
        doc.font('Helvetica-Bold').fontSize(14).text(`TOTAL FINAL: $${totalFinal.toFixed(2).replace('.', ',')} ARS`, { align: 'right' }).moveDown();

        doc.end();

    } catch (error) {
        console.error('Error general al crear reserva o factura:', error);
        res.status(500).json({ success: false, message: 'Error interno del servidor al procesar la reserva y generar la factura', details: error.message });
    }
});

// Iniciar la conexión a la DB y luego el servidor
conectarDB().then(() => {
    app.listen(PORT, () => {
        console.log(`Servidor Node.js escuchando en http://localhost:${PORT}`);
    });
});
