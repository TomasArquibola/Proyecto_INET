<?php
// config.php - Configuración de la base de datos
class Database {
    private $host = "localhost";
    private $db_name = "booking_app"; // Cambia por el nombre de tu BD
    private $username = "root";      // Cambia por tu usuario
    private $password = "";   // Cambia por tu contraseña
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                $this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// api.php - API REST para obtener los paquetes
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$request_method = $_SERVER["REQUEST_METHOD"];
$request_uri = $_SERVER["REQUEST_URI"];

// Parsear la URL para obtener el endpoint
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));
$endpoint = end($path_parts);

switch($request_method) {
    case 'GET':
        if ($endpoint == 'paquetes' || strpos($path, 'paquetes') !== false) {
            obtenerPaquetes($db);
        } elseif ($endpoint == 'paquete' && isset($_GET['id'])) {
            obtenerPaquete($db, $_GET['id']);
        } elseif ($endpoint == 'buscar' && isset($_GET['q'])) {
            buscarPaquetes($db, $_GET['q']);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Endpoint no encontrado"));
        }
        break;
    
    case 'POST':
        if ($endpoint == 'reservar') {
            crearReserva($db);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Endpoint no encontrado"));
        }
        break;
    
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Método no permitido"));
        break;
}

function obtenerPaquetes($db) {
    $query = "SELECT p.*, 
                     GROUP_CONCAT(
                         CONCAT(s.Tipo, ':', s.Descripcion, ':', s.Precio) 
                         SEPARATOR '|'
                     ) as servicios
              FROM paquete_turistico p 
              LEFT JOIN servicio_adicional s ON p.ID_Paquete = s.ID_Paquete 
              WHERE p.Disponible = 1 
              GROUP BY p.ID_Paquete 
              ORDER BY p.ID_Paquete";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $paquetes = array();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paquete = array(
            "id" => $row['ID_Paquete'],
            "nombre" => $row['Nombre'],
            "destino" => $row['Destino'],
            "descripcion" => $row['Descripcion'],
            "precio_base" => floatval($row['Precio_Base']),
            "disponible" => $row['Disponible'] == 1,
            "servicios_adicionales" => array()
        );
        
        if ($row['servicios']) {
            $servicios = explode('|', $row['servicios']);
            foreach ($servicios as $servicio) {
                $partes = explode(':', $servicio);
                if (count($partes) == 3) {
                    $paquete['servicios_adicionales'][] = array(
                        "tipo" => $partes[0],
                        "descripcion" => $partes[1],
                        "precio" => floatval($partes[2])
                    );
                }
            }
        }
        
        $paquetes[] = $paquete;
    }
    
    http_response_code(200);
    echo json_encode($paquetes);
}

function obtenerPaquete($db, $id) {
    $query = "SELECT * FROM paquete_turistico WHERE ID_Paquete = ? AND Disponible = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $paquete = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obtener servicios adicionales
        $query_servicios = "SELECT * FROM servicio_adicional WHERE ID_Paquete = ?";
        $stmt_servicios = $db->prepare($query_servicios);
        $stmt_servicios->bindParam(1, $id);
        $stmt_servicios->execute();
        
        $servicios = array();
        while ($servicio = $stmt_servicios->fetch(PDO::FETCH_ASSOC)) {
            $servicios[] = array(
                "id" => $servicio['ID_Servicio'],
                "tipo" => $servicio['Tipo'],
                "descripcion" => $servicio['Descripcion'],
                "precio" => floatval($servicio['Precio'])
            );
        }
        
        $resultado = array(
            "id" => $paquete['ID_Paquete'],
            "nombre" => $paquete['Nombre'],
            "destino" => $paquete['Destino'],
            "descripcion" => $paquete['Descripcion'],
            "precio_base" => floatval($paquete['Precio_Base']),
            "disponible" => $paquete['Disponible'] == 1,
            "servicios_adicionales" => $servicios
        );
        
        http_response_code(200);
        echo json_encode($resultado);
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "Paquete no encontrado"));
    }
}

function buscarPaquetes($db, $query_text) {
    $search_term = "%{$query_text}%";
    $query = "SELECT p.*, 
                     GROUP_CONCAT(
                         CONCAT(s.Tipo, ':', s.Descripcion, ':', s.Precio) 
                         SEPARATOR '|'
                     ) as servicios
              FROM paquete_turistico p 
              LEFT JOIN servicio_adicional s ON p.ID_Paquete = s.ID_Paquete 
              WHERE p.Disponible = 1 
              AND (p.Nombre LIKE ? OR p.Destino LIKE ? OR p.Descripcion LIKE ?)
              GROUP BY p.ID_Paquete 
              ORDER BY p.ID_Paquete";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $search_term);
    $stmt->bindParam(2, $search_term);
    $stmt->bindParam(3, $search_term);
    $stmt->execute();
    
    $paquetes = array();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paquete = array(
            "id" => $row['ID_Paquete'],
            "nombre" => $row['Nombre'],
            "destino" => $row['Destino'],
            "descripcion" => $row['Descripcion'],
            "precio_base" => floatval($row['Precio_Base']),
            "disponible" => $row['Disponible'] == 1,
            "servicios_adicionales" => array()
        );
        
        if ($row['servicios']) {
            $servicios = explode('|', $row['servicios']);
            foreach ($servicios as $servicio) {
                $partes = explode(':', $servicio);
                if (count($partes) == 3) {
                    $paquete['servicios_adicionales'][] = array(
                        "tipo" => $partes[0],
                        "descripcion" => $partes[1],
                        "precio" => floatval($partes[2])
                    );
                }
            }
        }
        
        $paquetes[] = $paquete;
    }
    
    http_response_code(200);
    echo json_encode($paquetes);
}

function crearReserva($db) {
    $data = json_decode(file_get_contents("php://input"));
    
    if (!empty($data->id_usuario) && !empty($data->id_paquete)) {
        // Obtener el precio del paquete
        $query_precio = "SELECT Precio_Base FROM paquete_turistico WHERE ID_Paquete = ?";
        $stmt_precio = $db->prepare($query_precio);
        $stmt_precio->bindParam(1, $data->id_paquete);
        $stmt_precio->execute();
        
        if ($stmt_precio->rowCount() > 0) {
            $paquete = $stmt_precio->fetch(PDO::FETCH_ASSOC);
            $total = $paquete['Precio_Base'];
            
            // Agregar servicios adicionales si los hay
            if (!empty($data->servicios_adicionales)) {
                $ids_servicios = implode(',', array_map('intval', $data->servicios_adicionales));
                $query_servicios = "SELECT SUM(Precio) as total_servicios FROM servicio_adicional WHERE ID_Servicio IN ($ids_servicios)";
                $stmt_servicios = $db->prepare($query_servicios);
                $stmt_servicios->execute();
                $servicios_total = $stmt_servicios->fetch(PDO::FETCH_ASSOC);
                $total += $servicios_total['total_servicios'];
            }
            
            // Crear la reserva
            $query = "INSERT INTO reserva (ID_Usuario, ID_Paquete, Fecha_Reserva, Total) VALUES (?, ?, NOW(), ?)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $data->id_usuario);
            $stmt->bindParam(2, $data->id_paquete);
            $stmt->bindParam(3, $total);
            
            if ($stmt->execute()) {
                $reserva_id = $db->lastInsertId();
                http_response_code(201);
                echo json_encode(array(
                    "message" => "Reserva creada exitosamente",
                    "id_reserva" => $reserva_id,
                    "total" => $total
                ));
            } else {
                http_response_code(500);
                echo json_encode(array("message" => "Error al crear la reserva"));
            }
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Paquete no encontrado"));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Datos incompletos"));
    }
}
?>
