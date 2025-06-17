<?php
// Ruta: C:\Users\ricar\OneDrive\Documentos\Inventario Farmacia\test_connection.php
header('Content-Type: text/plain; charset=utf-8');

// Configuración (¡cambia estos valores!)
$host = "localhost";
$user = "root";          // Usuario común en XAMPP
$pass = "";              // Contraseña (vacía en XAMPP por defecto)
$db   = "farmacia";

echo "🔍 Probando conexión a la base de datos...\n\n";

// 1. Conexión a MySQL
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("❌ Error conectando a MySQL: " . $conn->connect_error);
}
echo "✅ Conexión a MySQL exitosa\n";

// 2. Verificar existencia de la base de datos
if (!$conn->select_db($db)) {
    die("❌ La base de datos '$db' no existe");
}
echo "✅ Base de datos '$db' encontrada\n";

// 3. Verificar tabla medicamentos
$result = $conn->query("SHOW TABLES LIKE 'medicamentos'");
if ($result->num_rows == 0) {
    die("❌ La tabla 'medicamentos' no existe en la base de datos");
}
echo "✅ Tabla 'medicamentos' encontrada\n";

// 4. Contar registros
$count = $conn->query("SELECT COUNT(*) AS total FROM medicamentos")->fetch_assoc();
echo "📊 Total de medicamentos: " . $count['total'] . "\n";

// 5. Mostrar primeros 3 registros
echo "\n🔬 Primeros 3 medicamentos:\n";
$meds = $conn->query("SELECT nombre, stock FROM medicamentos LIMIT 3");
while ($m = $meds->fetch_assoc()) {
    echo "- {$m['nombre']} (Stock: {$m['stock']})\n";
}

$conn->close();
?>