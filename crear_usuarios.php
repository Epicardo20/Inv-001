<?php
// InventarioF/crear_usuarios.php
require_once 'php/config.php';  // Asegúrate de que la ruta sea correcta

// Función de encriptación
function encryptData($data, $key) {
    $method = 'aes-256-cbc';
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

// Clave de encriptación (deberías guardarla en config.php)
define('ENCRYPTION_KEY', 'una_clave_segura_de_32_caracteres_aqui_1234');

try {
    // Datos de usuarios a crear
    $usuarios = [
        [
            'username' => 'admin3',
            'password' => 'Admin123#',  // Contraseña en texto plano
            'rol' => 'admin'
        ],
        [
            'username' => 'empleado2',
            'password' => 'Empleado456#',
            'rol' => 'trabajador'
        ]
    ];

    foreach ($usuarios as $usuario) {
        // Cifrar la contraseña
        $hash = password_hash($usuario['password'], PASSWORD_BCRYPT);
        
        // Encriptar el username
        $username_encrypted = encryptData($usuario['username'], ENCRYPTION_KEY);
        
        // Insertar en la base de datos (actualiza los nombres de columnas según tu esquema)
        $stmt = $pdo->prepare("INSERT INTO usuarios (username, username_encrypted, password, rol) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $usuario['username'],  // Guardamos también el username sin encriptar por si necesitas búsquedas
            $username_encrypted,
            $hash,
            $usuario['rol']
        ]);
        
        echo "Usuario <strong>{$usuario['username']}</strong> creado exitosamente.<br>";
        echo "Username encriptado: <code>" . substr($username_encrypted, 0, 20) . "...</code><br>";
        echo "Contraseña original: <code>{$usuario['password']}</code><br>";
        echo "Hash de contraseña: <code>" . substr($hash, 0, 20) . "...</code><br><br>";
    }

    echo "✅ Todos los usuarios fueron creados. <strong>Borra este archivo (crear_usuarios.php) por seguridad.</strong>";
} catch (PDOException $e) {
    die("Error al crear usuarios: " . $e->getMessage());
}