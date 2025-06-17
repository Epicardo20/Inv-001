<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  die("Acceso denegado. <a href='auth/login.php'>Iniciar sesión</a>");
}
require_once 'config.php';

class Database {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
    }

    // Operaciones CRUD para medicamentos
    public function getMedicamentos($filtros = []) {
        $sql = "SELECT * FROM medicamentos WHERE 1=1";
        $params = [];
        $types = "";
        
        // Aplicar filtros
        if (!empty($filtros['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filtros['status'];
            $types .= "s";
        }
        
        if (!empty($filtros['stock_min'])) {
            $sql .= " AND stock <= ?";
            $params[] = $filtros['stock_min'];
            $types .= "i";
        }
        
        if (!empty($filtros['search'])) {
            $sql .= " AND (nombre LIKE ? OR lote LIKE ? OR receta LIKE ?)";
            $searchTerm = "%" . $filtros['search'] . "%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sss";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $medicamentos = [];
        while ($row = $result->fetch_assoc()) {
            $medicamentos[] = $row;
        }
        
        return $medicamentos;
    }

    public function getMedicamentoById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM medicamentos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function crearMedicamento($data) {
        $stmt = $this->conn->prepare("INSERT INTO medicamentos (nombre, presentacion, lote, stock, caducidad, proveedor, receta, categoria, precio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssissssd", 
            $data['nombre'],
            $data['presentacion'],
            $data['lote'],
            $data['stock'],
            $data['caducidad'],
            $data['proveedor'],
            $data['receta'],
            $data['categoria'],
            $data['precio']
        );
        return $stmt->execute();
    }

    public function actualizarMedicamento($id, $data) {
        $stmt = $this->conn->prepare("UPDATE medicamentos SET nombre = ?, presentacion = ?, lote = ?, stock = ?, caducidad = ?, proveedor = ?, receta = ?, categoria = ?, precio = ? WHERE id = ?");
        $stmt->bind_param("sssissssdi", 
            $data['nombre'],
            $data['presentacion'],
            $data['lote'],
            $data['stock'],
            $data['caducidad'],
            $data['proveedor'],
            $data['receta'],
            $data['categoria'],
            $data['precio'],
            $id
        );
        return $stmt->execute();
    }

    public function eliminarMedicamento($id) {
        $stmt = $this->conn->prepare("DELETE FROM medicamentos WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getEstadisticas() {
        $stats = [];
        
        // Total medicamentos
        $result = $this->conn->query("SELECT COUNT(*) as total FROM medicamentos");
        $stats['total'] = $result->fetch_assoc()['total'];
        
        // Medicamentos activos
        $result = $this->conn->query("SELECT COUNT(*) as activos FROM medicamentos WHERE status = 'active'");
        $stats['activos'] = $result->fetch_assoc()['activos'];
        
        // Por caducar (30 días)
        $result = $this->conn->query("SELECT COUNT(*) as por_caducar FROM medicamentos WHERE status = 'warning'");
        $stats['por_caducar'] = $result->fetch_assoc()['por_caducar'];
        
        // Caducados
        $result = $this->conn->query("SELECT COUNT(*) as caducados FROM medicamentos WHERE status = 'expired'");
        $stats['caducados'] = $result->fetch_assoc()['caducados'];
        
        // Bajo stock (<10)
        $result = $this->conn->query("SELECT COUNT(*) as bajo_stock FROM medicamentos WHERE stock < 10");
        $stats['bajo_stock'] = $result->fetch_assoc()['bajo_stock'];
        
        return $stats;
    }
}
 
?>