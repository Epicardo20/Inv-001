<?php
session_start();

// Configuración de headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-HTTP-Method-Override');

require_once __DIR__ . '/config.php';

// Verificar autenticación y rol de admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_rol'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
    exit;
}

// Manejar método OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Determinar el método HTTP real (para soportar override)
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
}

try {
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
            
        case 'POST':
            handlePostRequest();
            break;
            
        case 'PUT':
            handlePutRequest();
            break;
            
        case 'DELETE':
            handleDeleteRequest();
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error de base de datos',
        'details' => $e->getMessage()
    ]);
    error_log("Error en CRUD: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}

function handleGetRequest() {
    global $pdo;
    
    if (!isset($_GET['id'])) {
        // Si no hay ID, devolver todos los medicamentos
        $stmt = $pdo->query("SELECT * FROM medicamentos");
        $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $medicamentos]);
        return;
    }

    // Validar ID
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        return;
    }

    // Consulta preparada para un solo medicamento
    $stmt = $pdo->prepare("SELECT 
        id, nombre, presentacion, 
        IFNULL(lote, '') as lote, 
        stock, 
        DATE(caducidad) as caducidad, 
        IFNULL(proveedor, '') as proveedor, 
        IFNULL(receta, '') as receta,
        IFNULL(categoria, '') as categoria,
        precio,
        status
        FROM medicamentos WHERE id = ?");
    
    $stmt->execute([$id]);
    $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$medicamento) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Medicamento no encontrado']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $medicamento]);
}

function handlePostRequest() {
    global $pdo;
    
    // Leer y validar datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos']);
        return;
    }

    // Validar campos requeridos
    $required = ['nombre', 'presentacion', 'stock', 'caducidad'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "El campo $field es requerido"]);
            return;
        }
    }

    // Insertar nuevo medicamento
    $stmt = $pdo->prepare("INSERT INTO medicamentos 
        (nombre, presentacion, lote, stock, caducidad, proveedor, receta, categoria, precio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $success = $stmt->execute([
        $input['nombre'],
        $input['presentacion'],
        $input['lote'] ?? null,
        $input['stock'],
        $input['caducidad'],
        $input['proveedor'] ?? null,
        $input['receta'] ?? null,
        $input['categoria'] ?? null,
        $input['precio'] ?? 0
    ]);

    if ($success) {
        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM medicamentos WHERE id = ?");
        $stmt->execute([$id]);
        $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(201);
        echo json_encode(['success' => true, 'data' => $medicamento]);
    } else {
        throw new Exception('Error al crear el medicamento');
    }
}

function handlePutRequest() {
    global $pdo;
    
    // Validar ID
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
        return;
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        return;
    }

    // Leer y validar datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos']);
        return;
    }

    // Validar campos requeridos
    $required = ['nombre', 'presentacion', 'stock', 'caducidad'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "El campo $field es requerido"]);
            return;
        }
    }

    // Actualizar medicamento
    $stmt = $pdo->prepare("UPDATE medicamentos SET 
        nombre = ?, 
        presentacion = ?, 
        lote = ?, 
        stock = ?, 
        caducidad = ?, 
        proveedor = ?, 
        receta = ?,
        categoria = ?,
        precio = ?,
        status = CASE 
            WHEN ? < CURDATE() THEN 'expired'
            WHEN ? <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'warning'
            ELSE 'active'
        END
        WHERE id = ?");

    $success = $stmt->execute([
        $input['nombre'],
        $input['presentacion'],
        $input['lote'] ?? null,
        $input['stock'],
        $input['caducidad'],
        $input['proveedor'] ?? null,
        $input['receta'] ?? null,
        $input['categoria'] ?? null,
        $input['precio'] ?? 0,
        $input['caducidad'], // Para calcular status
        $input['caducidad'], // Para calcular status
        $id
    ]);

    if ($success) {
        // Obtener el medicamento actualizado
        $stmt = $pdo->prepare("SELECT * FROM medicamentos WHERE id = ?");
        $stmt->execute([$id]);
        $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $medicamento]);
    } else {
        throw new Exception('Error al actualizar el medicamento');
    }
}

function handleDeleteRequest() {
    global $pdo;
    
    // Validar ID
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
        return;
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        return;
    }

    // Verificar que el medicamento existe
    $stmt = $pdo->prepare("SELECT id FROM medicamentos WHERE id = ?");
    $stmt->execute([$id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Medicamento no encontrado']);
        return;
    }

    // Eliminar medicamento
    $stmt = $pdo->prepare("DELETE FROM medicamentos WHERE id = ?");
    $success = $stmt->execute([$id]);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Error al eliminar el medicamento');
    }
}