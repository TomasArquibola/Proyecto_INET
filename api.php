<?php
// api.php
require 'vendor/autoload.php'; // Carga las bibliotecas de Composer (Dompdf, PHPMailer)
include 'conexion.php'; // Incluye tu archivo de conexión a la base de datos

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json'); // Por defecto, la API responderá con JSON
header('Access-Control-Allow-Origin: *'); // Permite CORS para desarrollo. Ajusta en producción.
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS requests (pre-flight for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Obtener la ruta de la solicitud (parte después de /api.php/)
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script_name = dirname($_SERVER['SCRIPT_NAME']);
$endpoint = str_replace($script_name, '', $request_uri);
$endpoint = trim($endpoint, '/');

// Decodificar el cuerpo de la solicitud JSON para POST
$input = json_decode(file_get_contents('php://input'), true);

switch ($endpoint) {
    case 'paquetes':
        // Lógica para obtener todos los paquetes (GET)
        $sql = "SELECT p.ID_Paquete, p.Nombre, p.Destino, p.Descripcion, p.Precio_Base, p.Disponible,
                       GROUP_CONCAT(
                           CONCAT(s.ID_Servicio, ':', s.Tipo, ':', s.Descripcion, ':', s.Precio)
                           ORDER BY s.ID_Servicio SEPARATOR '|'
                       ) as servicios
                FROM paquete_turistico p
                LEFT JOIN servicio_adicional s ON p.ID_Paquete = s.ID_Paquete
                WHERE p.Disponible = 1
                GROUP BY p.ID_Paquete
                ORDER BY p.ID_Paquete";
        $result = $conn->query($sql);

        $paquetes = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $serviciosAdicionales = [];
                if ($row['servicios']) {
                    foreach (explode('|', $row['servicios']) as $servicioStr) {
                        list($id, $tipo, $descripcion, $precio) = explode(':', $servicioStr);
                        if ($id && $tipo && $descripcion && $precio) {
                            $serviciosAdicionales[] = [
                                'id' => (int)$id,
                                'tipo' => $tipo,
                                'descripcion' => $descripcion,
                                'precio' => (float)$precio
                            ];
                        }
                    }
                }
                $paquetes[] = [
                    'id' => (int)$row['ID_Paquete'],
                    'nombre' => $row['Nombre'],
                    'destino' => $row['Destino'],
                    'descripcion' => $row['Descripcion'],
                    'precio_base' => (float)$row['Precio_Base'],
                    'disponible' => (bool)$row['Disponible'],
                    'servicios_adicionales' => $serviciosAdicionales
                ];
            }
        }
        echo json_encode($paquetes);
        break;

    case 'paquete':
        // Lógica para obtener un paquete específico (GET)
        $id_paquete = $_GET['id'] ?? null;
        if (!$id_paquete || !is_numeric($id_paquete)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de paquete no proporcionado o inválido.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT ID_Paquete, Nombre, Destino, Descripcion, Precio_Base, Disponible FROM paquete_turistico WHERE ID_Paquete = ? AND Disponible = 1");
        $stmt->bind_param("i", $id_paquete);
        $stmt->execute();
        $result = $stmt->get_result();
        $paquete = $result->fetch_assoc();
        $stmt->close();

        if (!$paquete) {
            http_response_code(404);
            echo json_encode(['error' => 'Paquete no encontrado o no disponible.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT ID_Servicio, Tipo, Descripcion, Precio FROM servicio_adicional WHERE ID_Paquete = ?");
        $stmt->bind_param("i", $id_paquete);
        $stmt->execute();
        $result = $stmt->get_result();
        $servicios = [];
        while ($row = $result->fetch_assoc()) {
            $servicios[] = [
                'id' => (int)$row['ID_Servicio'],
                'tipo' => $row['Tipo'],
                'descripcion' => $row['Descripcion'],
                'precio' => (float)$row['Precio']
            ];
        }
        $stmt->close();

        $paquete['ID_Paquete'] = (int)$paquete['ID_Paquete'];
        $paquete['Precio_Base'] = (float)$paquete['Precio_Base'];
        $paquete['Disponible'] = (bool)$paquete['Disponible'];
        $paquete['servicios_adicionales'] = $servicios;
        
        echo json_encode($paquete);
        break;

    case 'buscar':
        // Lógica para buscar paquetes (GET)
        $query = $_GET['q'] ?? '';
        if (empty($query)) {
            http_response_code(400);
            echo json_encode(['error' => 'Parámetro de búsqueda "q" requerido.']);
            exit();
        }
        $searchTerm = "%" . $query . "%";

        $sql = "SELECT p.ID_Paquete, p.Nombre, p.Destino, p.Descripcion, p.Precio_Base, p.Disponible,
                       GROUP_CONCAT(
                           CONCAT(s.ID_Servicio, ':', s.Tipo, ':', s.Descripcion, ':', s.Precio)
                           ORDER BY s.ID_Servicio SEPARATOR '|'
                       ) as servicios
                FROM paquete_turistico p
                LEFT JOIN servicio_adicional s ON p.ID_Paquete = s.ID_Paquete
                WHERE p.Disponible = 1 AND (p.Nombre LIKE ? OR p.Destino LIKE ? OR p.Descripcion LIKE ?)
                GROUP BY p.ID_Paquete
                ORDER BY p.ID_Paquete";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();

        $paquetes = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $serviciosAdicionales = [];
                if ($row['servicios']) {
                    foreach (explode('|', $row['servicios']) as $servicioStr) {
                        list($id, $tipo, $descripcion, $precio) = explode(':', $servicioStr);
                        if ($id && $tipo && $descripcion && $precio) {
                            $serviciosAdicionales[] = [
                                'id' => (int)$id,
                                'tipo' => $tipo,
                                'descripcion' => $descripcion,
                                'precio' => (float)$precio
                            ];
                        }
                    }
                }
                $paquetes[] = [
                    'id' => (int)$row['ID_Paquete'],
                    'nombre' => $row['Nombre'],
                    'destino' => $row['Destino'],
                    'descripcion' => $row['Descripcion'],
                    'precio_base' => (float)$row['Precio_Base'],
                    'disponible' => (bool)$row['Disponible'],
                    'servicios_adicionales' => $serviciosAdicionales
                ];
            }
        }
        $stmt->close();
        echo json_encode($paquetes);
        break;

    case 'reservar':
        // Lógica para crear una reserva (POST)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit();
        }

        $id_usuario = $input['id_usuario'] ?? null;
        $id_paquete = $input['id_paquete'] ?? null;
        $servicios_adicionales = $input['servicios_adicionales'] ?? []; // Array de IDs
        $datos_comprador = $input['datos_comprador'] ?? [];

        if (!$id_usuario || !$id_paquete || !isset($datos_comprador['nombre']) || empty($datos_comprador['nombre'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Datos incompletos para la reserva: ID de usuario, ID de paquete y nombre del comprador son requeridos.']);
            exit();
        }

        $serviciosStr = implode(',', array_map('intval', $servicios_adicionales)); // Asegura que sean enteros

        try {
            // Iniciar transacción
            $conn->begin_transaction();

            // 1. Llamar al procedimiento almacenado para crear la reserva
            $stmt = $conn->prepare("CALL CrearReservaConServicios(?, ?, ?)");
            $stmt->bind_param("iis", $id_usuario, $id_paquete, $serviciosStr);
            $stmt->execute();
            $stmt->close();

            // Obtener el ID de la última reserva insertada (si el SP lo retorna o lo manejas de otra forma)
            // Esto es un placeholder, asumiendo que el SP maneja la inserción.
            // Para obtener el ID, podríamos hacer un SELECT MAX(ID_Reserva) o modificar el SP para que lo devuelva.
            // Por simplicidad, asumiremos que la reserva se creó correctamente y generamos un ID simulado para la factura.
            $id_reserva_creada = $conn->insert_id ?: rand(1000, 9999); 

            // 2. Obtener los detalles completos del paquete y servicios para la factura
            $stmt = $conn->prepare("SELECT Nombre, Destino, Precio_Base FROM paquete_turistico WHERE ID_Paquete = ?");
            $stmt->bind_param("i", $id_paquete);
            $stmt->execute();
            $result = $stmt->get_result();
            $paqueteDetalles = $result->fetch_assoc();
            $stmt->close();

            if (!$paqueteDetalles) {
                $conn->rollback();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Paquete no encontrado para generar factura.']);
                exit();
            }

            $serviciosFactura = [];
            $totalServiciosAdicionales = 0;
            if (!empty($servicios_adicionales)) {
                $placeholders = implode(',', array_fill(0, count($servicios_adicionales), '?'));
                $types = str_repeat('i', count($servicios_adicionales));
                
                $stmt = $conn->prepare("SELECT Tipo, Descripcion, Precio FROM servicio_adicional WHERE ID_Servicio IN ($placeholders)");
                $stmt->bind_param($types, ...$servicios_adicionales);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $serviciosFactura[] = [
                        'tipo' => $row['Tipo'],
                        'descripcion' => $row['Descripcion'],
                        'precio' => (float)$row['Precio']
                    ];
                    $totalServiciosAdicionales += (float)$row['Precio'];
                }
                $stmt->close();
            }

            $totalFinal = (float)$paqueteDetalles['Precio_Base'] + $totalServiciosAdicionales;

            // 3. Generar el HTML de la factura para Dompdf
            ob_start(); // Iniciar buffer de salida para capturar HTML
            $invoiceNumber = "INV-" . str_pad($id_reserva_creada, 6, '0', STR_PAD_LEFT);
            $emissionDate = date('d/m/Y'); // Formato de fecha para Argentina
            $emisorNombre = "Agencia de Viajes Maravilla";
            $emisorNIF = "30-12345678-9"; // NIF/CUIT simulado para Argentina
            $emisorDireccion = "Av. Ficticia 123, CABA, Argentina";
            $compradorNombre = htmlspecialchars($datos_comprador['nombre']);
            $compradorEmail = htmlspecialchars($datos_comprador['email'] ?? '');
            $compradorDireccion = htmlspecialchars($datos_comprador['direccion'] ?? '');
            $paqueteNombre = htmlspecialchars($paqueteDetalles['Nombre']);
            $paqueteDestino = htmlspecialchars($paqueteDetalles['Destino']);
            $paquetePrecioBase = number_format($paqueteDetalles['Precio_Base'], 2, ',', '.');
            $totalFinalFormateado = number_format($totalFinal, 2, ',', '.');
            
            // HTML para la factura
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title>Factura de Reserva</title>
                <style>
                    body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; margin: 20px; }
                    .header, .footer { width: 100%; text-align: center; position: fixed; }
                    .header { top: 0; }
                    .footer { bottom: 0; font-size: 8px; color: #555; }
                    .invoice-box { max-width: 800px; margin: auto; padding: 20px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, .15); font-size: 12px; line-height: 18px; color: #555; }
                    .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
                    .invoice-box table td { padding: 5px; vertical-align: top; }
                    .invoice-box table tr td:nth-child(2) { text-align: right; }
                    .invoice-box table tr.top table td { padding-bottom: 20px; }
                    .invoice-box table tr.top table td.title { font-size: 45px; line-height: 45px; color: #333; }
                    .invoice-box table tr.information table td { padding-bottom: 20px; }
                    .invoice-box table tr.heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; padding: 8px 5px; }
                    .invoice-box table tr.details td { padding-bottom: 15px; }
                    .invoice-box table tr.item td { border-bottom: 1px solid #eee; padding: 8px 5px; }
                    .invoice-box table tr.item.last td { border-bottom: none; }
                    .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
                    .text-right { text-align: right; }
                    .text-left { text-align: left; }
                    .title-section { text-align: center; margin-bottom: 30px; }
                    .info-section { margin-bottom: 20px; border: 1px solid #eee; padding: 10px; border-radius: 5px; }
                    .info-section h3 { margin-top: 0; margin-bottom: 10px; font-size: 14px; color: #333; }
                    .info-section p { margin: 0; }
                    .total-summary { font-size: 14px; font-weight: bold; margin-top: 20px; text-align: right; }
                    .section-title { font-size: 16px; font-weight: bold; margin-top: 20px; margin-bottom: 10px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                </style>
            </head>
            <body>
                <div class="invoice-box">
                    <div class="title-section">
                        <h1>FACTURA</h1>
                        <p><strong>Nº Factura:</strong> <?php echo $invoiceNumber; ?></p>
                        <p><strong>Fecha de Emisión:</strong> <?php echo $emissionDate; ?></p>
                    </div>

                    <div class="info-section">
                        <table width="100%">
                            <tr>
                                <td width="50%" class="text-left">
                                    <h3>Datos del Emisor:</h3>
                                    <p><strong>Nombre:</strong> <?php echo $emisorNombre; ?></p>
                                    <p><strong>NIF/CUIT:</strong> <?php echo $emisorNIF; ?></p>
                                    <p><strong>Dirección:</strong> <?php echo $emisorDireccion; ?></p>
                                </td>
                                <td width="50%" class="text-left">
                                    <h3>Datos del Receptor:</h3>
                                    <p><strong>Nombre:</strong> <?php echo $compradorNombre; ?></p>
                                    <?php if (!empty($compradorEmail)) : ?>
                                        <p><strong>Email:</strong> <?php echo $compradorEmail; ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($compradorDireccion)) : ?>
                                        <p><strong>Dirección:</strong> <?php echo $compradorDireccion; ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="section-title">Detalle de la Reserva</div>
                    <table cellpadding="0" cellspacing="0">
                        <tr class="heading">
                            <td class="text-left" style="width: 50%;">Descripción</td>
                            <td class="text-right" style="width: 15%;">Cantidad</td>
                            <td class="text-right" style="width: 20%;">Precio Unitario</td>
                            <td class="text-right" style="width: 15%;">Total</td>
                        </tr>

                        <tr class="item">
                            <td class="text-left"><?php echo $paqueteNombre; ?> (<?php echo $paqueteDestino; ?>)</td>
                            <td class="text-right">1</td>
                            <td class="text-right">$<?php echo $paquetePrecioBase; ?> ARS</td>
                            <td class="text-right">$<?php echo $paquetePrecioBase; ?> ARS</td>
                        </tr>

                        <?php foreach ($serviciosFactura as $service) : ?>
                        <tr class="item">
                            <td class="text-left" style="padding-left: 20px;">- <?php echo htmlspecialchars($service['tipo']); ?>: <?php echo htmlspecialchars($service['descripcion']); ?></td>
                            <td class="text-right">1</td>
                            <td class="text-right">$<?php echo number_format($service['precio'], 2, ',', '.'); ?> ARS</td>
                            <td class="text-right">$<?php echo number_format($service['precio'], 2, ',', '.'); ?> ARS</td>
                        </tr>
                        <?php endforeach; ?>

                        <tr class="total">
                            <td colspan="3" class="text-right"></td>
                            <td class="text-right">
                                <br>
                                Total Final: $<?php echo $totalFinalFormateado; ?> ARS
                            </td>
                        </tr>
                    </table>
                </div>
            </body>
            </html>
            <?php
            $html = ob_get_clean(); // Capturar el HTML generado

            // 4. Generar el PDF con Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true); // Necesario si usas CSS externos o imágenes remotas
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfOutput = $dompdf->output(); // Obtener el contenido del PDF

            // 5. Enviar el PDF por email (simulado o real)
            $mail = new PHPMailer(true); // Pasar `true` habilita excepciones para errores

            try {
                // Configuración del servidor SMTP (ejemplo con Gmail, necesitas habilitar "App Passwords" o "less secure apps" si no usas 2FA)
                // O usa un servicio como SendGrid, Mailgun, etc.
                // Para pruebas, puedes usar un servicio como Mailtrap.io para capturar los correos.
                /*
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // O tu servidor SMTP
                $mail->SMTPAuth = true;
                $mail->Username = 'tu_correo@gmail.com'; // Tu dirección de correo
                $mail->Password = 'tu_contraseña_o_app_password'; // Tu contraseña o contraseña de aplicación
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // O PHPMailer::ENCRYPTION_STARTTLS
                $mail->Port = 465; // Puerto SMTP (465 para SMTPS, 587 para STARTTLS)
                */

                // Si estás depurando y no quieres usar un SMTP real, puedes usar esto:
                // $mail->isMail(); // Usa la función mail() de PHP, no requiere SMTP
                // Para simplificar la prueba y no depender de un servidor SMTP real:
                // Simulamos el envío, pero no se enviará físicamente sin configuración SMTP.
                error_log("Simulando envío de factura al email: " . ($datos_comprador['email'] ?? 'correo de prueba'));

                $mail->setFrom('no-reply@tuagencia.com', 'Agencia de Viajes Maravilla');
                $mail->addAddress($datos_comprador['email'] ?? 'test@example.com', $datos_comprador['nombre'] ?? 'Cliente'); // Email del comprador

                $mail->isHTML(true);
                $mail->Subject = 'Factura de tu Reserva - ' . $paqueteNombre;
                $mail->Body    = '
                    <p>Estimado/a ' . $compradorNombre . ',</p>
                    <p>Gracias por tu reserva con nosotros. Adjuntamos la factura de tu paquete turístico.</p>
                    <p><strong>Detalles de la Reserva:</strong></p>
                    <ul>
                        <li>Paquete: ' . $paqueteNombre . '</li>
                        <li>Destino: ' . $paqueteDestino . '</li>
                        <li>Total: $' . $totalFinalFormateado . ' ARS</li>
                    </ul>
                    <p>Atentamente,<br/>Tu Agencia de Viajes</p>
                ';
                $mail->AltBody = 'Gracias por tu reserva con nosotros. Adjuntamos la factura de tu paquete turístico. Total: $' . $totalFinalFormateado . ' ARS.';

                $mail->addStringAttachment($pdfOutput, 'factura_reserva_' . $invoiceNumber . '.pdf', 'base64', 'application/pdf');

                // $mail->send(); // Descomentar para enviar realmente el correo si el SMTP está configurado
                // error_log('Mensaje de factura enviado a: ' . ($datos_comprador['email'] ?? 'test@example.com'));

            } catch (Exception $e) {
                error_log("Error al enviar el email de factura: {$mail->ErrorInfo}");
                // No lanzamos una excepción al usuario final si el email falla,
                // ya que la reserva principal ya fue creada.
            }

            // 6. Confirmar transacción y enviar respuesta al frontend
            $conn->commit();
            http_response_code(201); // Created
            echo json_encode([
                'success' => true,
                'message' => 'Reserva creada con éxito y factura generada.',
                'id_reserva' => $id_reserva_creada,
                'total' => $totalFinal,
                'pdf_base64' => base64_encode($pdfOutput) // Enviar el PDF como base64
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al procesar la reserva: ' . $e->getMessage()]);
            error_log('Error en reserva: ' . $e->getMessage());
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint no encontrado.']);
        break;
}

$conn->close();
?>
