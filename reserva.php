<?php
// reserva.php
include 'conexion.php'; // Incluye el archivo de conexión

$package = null;
$services = [];
$totalBase = 0;

// Obtener el ID del paquete de la URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $packageId = (int)$_GET['id'];

    // Obtener detalles del paquete
    $stmt = $conn->prepare("SELECT ID_Paquete, Nombre, Destino, Descripcion, Precio_Base FROM paquete_turistico WHERE ID_Paquete = ?");
    $stmt->bind_param("i", $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $package = $result->fetch_assoc();
        $totalBase = (float) $package['Precio_Base'];
    }
    $stmt->close();

    // Obtener servicios adicionales para este paquete
    $stmt = $conn->prepare("SELECT ID_Servicio, Tipo, Descripcion, Precio FROM servicio_adicional WHERE ID_Paquete = ?");
    $stmt->bind_param("i", $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $stmt->close();

} else {
    // Si no se proporcionó un ID válido, redirige o muestra un error
    nav("Location: index.php"); // Redirige al catálogo
    exit();
}

$conn->close();

if (!$package) {
    // Si el paquete no se encontró en la base de datos
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Error</title>";
    echo "<script src='https://cdn.tailwindcss.com'></script></head><body>";
    echo "<div>";
    echo "<h1>Paquete no encontrado</h1>";
    echo "<p>El paquete turístico solicitado no existe o no está disponible.</p>";
    echo "<a href='index.php'>Volver al Catálogo</a>";
    echo "</div></body></html>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulka - Reservar: <?php echo htmlspecialchars($package['Nombre']); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="p-6">

    <!-- Barra de navegación -->
    <?php
        include 'nav.php';
    ?>

    <div class="reserva-container">
        <h1>Confirmar Reserva de Paquete</h1>

        <div>
            <h2><?php echo htmlspecialchars($package['Nombre']); ?></h2>
            <p><strong>Destino:</strong> <?php echo htmlspecialchars($package['Destino']); ?></p>
            <p><?php echo htmlspecialchars($package['Descripcion']); ?></p>
            <p>Precio Base: $<?php echo number_format($package['Precio_Base'], 2, ',', '.'); ?> ARS</p>
        </div>

        <form id="bookingForm">
            <input type="hidden" id="packageId" value="<?php echo htmlspecialchars($package['ID_Paquete']); ?>">
            <input type="hidden" id="basePrice" value="<?php echo htmlspecialchars($package['Precio_Base']); ?>">
            <input type="hidden" id="userId" value="3"> 
            
            <h2>Datos del Comprador</h2>
            <div>
                <div>
                    <label for="buyerName">Nombre Completo:</label>
                    <input type="text" id="buyerName" name="buyerName" required>
                </div>
                <div>
                    <label for="buyerEmail">Email:</label>
                    <input type="email" id="buyerEmail" name="buyerEmail" required>
                </div>
                <div>
                    <label for="buyerAddress">Dirección (Opcional):</label>
                    <input type="text" id="buyerAddress" name="buyerAddress">
                </div>
            </div>

            <h2>Seleccionar Servicios Adicionales</h2>
            <div id="services-container">
                <?php if (count($services) > 0): ?>
                    <?php foreach ($services as $service): ?>
                        <div>
                            <input type="checkbox" id="service_<?php echo htmlspecialchars($service['ID_Servicio']); ?>"
                                   name="services[]" value="<?php echo htmlspecialchars($service['ID_Servicio']); ?>"
                                   data-price="<?php echo htmlspecialchars($service['Precio']); ?>">
                            <label for="service_<?php echo htmlspecialchars($service['ID_Servicio']); ?>">
                                <?php echo htmlspecialchars($service['Tipo']); ?>: <?php echo htmlspecialchars($service['Descripcion']); ?>
                            </label>
                            <span>$<?php echo number_format($service['Precio'], 2, ',', '.'); ?> ARS</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-600 italic">No hay servicios adicionales disponibles para este paquete.</p>
                <?php endif; ?>
            </div>
            
            <div id="reserveEnd">
                <p>
                    Total Estimado: <span id="totalPrice">$<?php echo number_format($totalBase, 2, ',', '.'); ?> ARS</span>
                </p>
                <a href="index.php"><button class="cancelButton">Cancelar</button></a>
                <button type="submit" class="reserveButton">Confirmar Reserva y Obtener Factura</button>
            </div>
        </form>
    </div>

    <div id="messageBox">
        <div class="message-content">
            <p id="messageText"></p>
            <button id="closeMessage">Cerrar</button>
            <button id="downloadInvoice">Descargar Factura</button>
        </div>
    </div>

    <script>
        const API_BASE_URL = 'http://localhost/tu-proyecto'; // Ajusta esto a la ruta de tu API PHP
        // Por ejemplo, si tu api.php está en la raíz de tu servidor web, sería 'http://localhost'
        // Si está en 'http://localhost/mi-app/', entonces sería 'http://localhost/mi-app'

        document.addEventListener("DOMContentLoaded", () => {
            const bookingForm = document.getElementById("bookingForm");
            const packageIdInput = document.getElementById("packageId");
            const basePriceInput = document.getElementById("basePrice");
            const serviceCheckboxes = document.querySelectorAll('input[name="services[]"]');
            const totalPriceSpan = document.getElementById("totalPrice");

            const buyerNameInput = document.getElementById("buyerName");
            const buyerEmailInput = document.getElementById("buyerEmail");
            const buyerAddressInput = document.getElementById("buyerAddress");
            const userIdInput = document.getElementById("userId"); // Assuming this exists for now

            const messageBox = document.getElementById("messageBox");
            const messageText = document.getElementById("messageText");
            const closeMessageButton = document.getElementById("closeMessage");
            const downloadInvoiceButton = document.getElementById("downloadInvoice");

            let currentPdfBase64 = null; // Para almacenar el PDF en base64

            // Función para mostrar mensajes personalizados
            function showMessage(message, showDownload = false) {
                messageText.textContent = message;
                messageBox.classList.remove('hidden');
                if (showDownload) {
                    downloadInvoiceButton.classList.remove('hidden');
                } else {
                    downloadInvoiceButton.classList.add('hidden');
                }
            }

            // Event listener para cerrar el cuadro de mensaje
            closeMessageButton.addEventListener('click', () => {
                messageBox.classList.add('hidden');
                downloadInvoiceButton.classList.add('hidden'); // Ocultar también al cerrar
                // Opcional: Recargar la página o redirigir después de un mensaje de éxito
                if (messageText.textContent.includes("éxito")) {
                     window.location.href = "index.php"; // Redirige al catálogo
                }
            });

            // Event listener para descargar la factura
            downloadInvoiceButton.addEventListener('click', () => {
                if (currentPdfBase64) {
                    const link = document.createElement('a');
                    link.href = `data:application/pdf;base64,${currentPdfBase64}`;
                    link.download = `factura_reserva_${new Date().getTime()}.pdf`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    showMessage("No hay factura disponible para descargar.");
                }
            });

            // Función para calcular y actualizar el precio total
            function updateTotalPrice() {
                let currentTotal = parseFloat(basePriceInput.value);
                serviceCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        currentTotal += parseFloat(checkbox.dataset.price);
                    }
                });
                totalPriceSpan.textContent = `$${currentTotal.toFixed(2).replace('.', ',')} ARS`;
            }

            // Actualizar el total cuando se seleccionan/deseleccionan servicios
            serviceCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateTotalPrice);
            });

            // Inicializar el total al cargar la página
            updateTotalPrice();

            // Manejar el envío del formulario de reserva
            bookingForm.addEventListener("submit", async (e) => {
                e.preventDefault();

                const selectedServices = Array.from(serviceCheckboxes)
                    .filter(checkbox => checkbox.checked)
                    .map(checkbox => parseInt(checkbox.value)); // Asegura que sean números enteros

                const datosComprador = {
                    nombre: buyerNameInput.value.trim(),
                    email: buyerEmailInput.value.trim(),
                    direccion: buyerAddressInput.value.trim() // Opcional
                };

                // Simple validación de datos del comprador
                if (!datosComprador.nombre || !datosComprador.email) {
                    showMessage("Por favor, completa tu nombre y email para continuar.");
                    return;
                }

                const bookingData = {
                    id_usuario: parseInt(userIdInput.value), // Asegura que sea un número
                    id_paquete: parseInt(packageIdInput.value), // Asegura que sea un número
                    servicios_adicionales: selectedServices, // Array de IDs de servicios
                    datos_comprador: datosComprador
                };

                try {
                    const response = await fetch(`${API_BASE_URL}/api.php/reservar`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(bookingData)
                    });

                    const result = await response.json();

                    if (result.success) {
                        currentPdfBase64 = result.pdf_base64; // Guardar el PDF recibido
                        showMessage(`¡Reserva realizada con éxito! Total: $${parseFloat(result.total).toFixed(2).replace('.', ',')} ARS.`, true); // Mostrar botón de descarga
                    } else {
                        showMessage("Error al procesar la reserva: " + (result.message || "Error desconocido"));
                    }
                } catch (error) {
                    console.error("Error en la solicitud de reserva:", error);
                    showMessage("Error de conexión al servidor. Inténtalo de nuevo.");
                }
            });
        });
    </script>
</body>
</html>

