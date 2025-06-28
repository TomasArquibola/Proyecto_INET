<?php
// index.php
// Incluye el archivo de conexión a la base de datos
include 'conexion.php';

$ofertas = []; // Array para almacenar los datos de los paquetes turísticos

// Consulta para obtener los paquetes turísticos disponibles
// Aquí asumimos que Precio_Base es el precio en oferta, y el Precio_Original es un 20% más alto.
// Idealmente, deberías tener una columna `Precio_Oferta` o `Porcentaje_Descuento` en tu tabla `paquete_turistico`.
$sql = "SELECT ID_Paquete, Nombre, Destino, Descripcion, Precio_Base FROM paquete_turistico WHERE Disponible = 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $precioBase = (float) $row['Precio_Base'];
        $precioOriginal = $precioBase * 1.20; // Asumimos un 20% de descuento sobre el precio original

        // Determinar si hay descuento para mostrar "En oferta"
        // Si Precio_Base es menor que Precio_Original (calculado), entonces está en oferta
        $tieneDescuento = ($precioBase < $precioOriginal);

        $ofertas[] = [
            'id' => $row['ID_Paquete'],
            'titulo' => $row['Nombre'],
            'ubicacion' => $row['Destino'],
            'descripcion' => $row['Descripcion'], // Agregamos la descripción para el detalle
            'precioBase' => number_format($precioBase, 2, ',', '.') . ' ARS', // Formato para pesos argentinos
            'precioOriginal' => number_format($precioOriginal, 2, ',', '.') . ' ARS', // Formato para pesos argentinos
            'descuento' => $tieneDescuento
        ];
    }
} else {
    // Si no hay resultados o hay un error en la consulta
    error_log("Error al obtener paquetes: " . $conn->error);
}

// Cierra la conexión a la base de datos
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulka</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <!-- Barra de navegación -->
    <?php
        include 'nav.php';
    ?>

    <div id="pageContent">
        <h1>Nuestros Paquetes Turísticos</h1>

        <div id="offers">
            <!-- Las tarjetas de los paquetes se cargarán aquí -->
        </div>
    </div>

    <!-- Contenedor para el cuadro de mensaje personalizado -->
    <div id="messageBox" class="message-box hidden">
        <div class="message-content">
            <p id="messageText" class="text-lg font-medium text-gray-800 mb-4"></p>
            <button id="closeMessage" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Cerrar</button>
        </div>
    </div>

    <script>
        // Pasa los datos de PHP a JavaScript
        const productosData = <?php echo json_encode($ofertas); ?>;

        document.addEventListener("DOMContentLoaded", () => {
            const offersContainer = document.getElementById("offers");
            const searchForm = document.getElementById("searchForm");
            const searchQueryInput = document.getElementById("searchQuery");
            const messageBox = document.getElementById("messageBox");
            const messageText = document.getElementById("messageText");
            const closeMessageButton = document.getElementById("closeMessage");

            // Función para mostrar mensajes personalizados
            function showMessage(message) {
                messageText.textContent = message;
                messageBox.classList.remove('hidden');
            }

            // Event listener para cerrar el cuadro de mensaje
            closeMessageButton.addEventListener('click', () => {
                messageBox.classList.add('hidden');
            });

            // Función para renderizar las tarjetas
            function renderCards(data) {
                offersContainer.innerHTML = ''; // Limpia el contenedor antes de renderizar
                if (data.length === 0) {
                    offersContainer.innerHTML = '<p>No se encontraron paquetes con esos criterios de búsqueda.</p>';
                    return;
                }
                data.forEach(producto => {
                    const card = document.createElement("div");
                    card.className = "card"; // Clases base para la tarjeta

                    card.innerHTML = `
                        <img src="https://placehold.co/300x160/2563eb/ffffff?text=${encodeURIComponent(producto.titulo)}" alt="${producto.titulo}"/>
                        <div class="card-body">
                            <h3>${producto.titulo}</h3>
                            <p class='location'>${producto.ubicacion}</p>
                            ${producto.descuento ? "<span class='saleCard'>En oferta</span>" : ""}
                            <p class='price'>
                                ${producto.descuento ? `<del>${producto.precioOriginal}</del>` : ''}
                                <span class="${producto.descuento ? 'text-green-600' : 'text-blue-600'}">${producto.precioBase}</span>
                            </p>
                            <button onclick="reservar(${producto.id})">
                                Reservar
                            </button>
                        </div>
                    `;
                    offersContainer.appendChild(card);
                });
            }

            // Renderiza todas las tarjetas al cargar la página
            renderCards(productosData);

            // Manejador del formulario de búsqueda
            searchForm.addEventListener("submit", async (e) => {
                e.preventDefault();
                const query = searchQueryInput.value.trim();

                if (query === "") {
                    renderCards(productosData); // Muestra todos los productos si la búsqueda está vacía
                    return;
                }

                try {
                    // Realiza una petición fetch a un endpoint PHP para buscar paquetes
                    const response = await fetch(`buscar_paquetes.php?query=${encodeURIComponent(query)}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const searchResults = await response.json();
                    renderCards(searchResults); // Renderiza los resultados de la búsqueda
                } catch (error) {
                    console.error("Error al buscar paquetes:", error);
                    showMessage("Error al realizar la búsqueda. Inténtalo de nuevo más tarde.");
                    renderCards([]); // Muestra un array vacío para indicar que no hay resultados o hubo un error
                }
            });
        });

        // La función reservar ahora redirige a una página de detalle de reserva
        function reservar(packageId) {
            // Redirige al usuario a una página de reserva detallada
            // Donde podrá ver más información del paquete y servicios adicionales.
            window.location.href = `reserva.php?id=${packageId}`;
        }
    </script>
</body>
</html>
