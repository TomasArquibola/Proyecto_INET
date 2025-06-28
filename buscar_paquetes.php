<?php
// procesar_reserva.php
include 'conexion.php'; // Incluye el archivo de conexión

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON

$response = ['success' => false, 'message' => ''];

// Verifica que la solicitud sea POST y que el cuerpo no esté vacío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Valida los datos recibidos
    if (isset($data['id_usuario'], $data['id_paquete'])) {
        $id_usuario = (int)$data['id_usuario'];
        $id_paquete = (int)$data['id_paquete'];
        // Asegúrate de que $data['servicios'] es una cadena (puede ser vacía)
        $servicios_str = isset($data['servicios']) ? $data['servicios'] : '';

        // Llama al procedimiento almacenado
        // Es importante usar CALL y no solo el nombre del procedimiento si se usan parámetros IN/OUT
        $stmt = $conn->prepare("CALL CrearReservaConServicios(?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("iis", $id_usuario, $id_paquete, $servicios_str);

            if ($stmt->execute()) {
                // Si la ejecución fue exitosa, obtenemos el total de la reserva
                // El procedimiento almacenado INSERT INTO Reserva... no devuelve directamente el total,
                // así que para obtenerlo, tendríamos que consultarlo de nuevo o modificar el SP para devolverlo.
                // Por simplicidad y para mostrar un valor al usuario, podemos recalcularlo o
                // si el SP pudiera devolverlo, lo capturaríamos.
                // Por ahora, simplemente confirmamos el éxito y dejamos que JS muestre el total que calculó.
                $response['success'] = true;
                $response['message'] = 'Reserva creada con éxito.';

                // Opcional: Podrías buscar la reserva recién creada para obtener el total exacto si lo necesitas en el backend
                // Por ejemplo: $last_insert_id = $conn->insert_id;
                // $result = $conn->query("SELECT Total FROM Reserva WHERE ID_Reserva = $last_insert_id");
                // if ($result && $row = $result->fetch_assoc()) {
                //     $response['total'] = number_format((float)$row['Total'], 2, ',', '.');
                // }

            } else {
                $response['message'] = 'Error al ejecutar el procedimiento almacenado: ' . $stmt->error;
                error_log("Error al ejecutar SP: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $response['message'] = 'Error al preparar la llamada al procedimiento almacenado: ' . $conn->error;
            error_log("Error al preparar SP: " . $conn->error);
        }
    } else {
        $response['message'] = 'Datos incompletos para la reserva.';
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

$conn->close();
echo json_encode($response);
?>
