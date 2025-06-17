<?php
session_start();
require_once __DIR__ . '/../config.php'; // Usa __DIR__ para ruta absoluta

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos vacíos
        if (empty($_POST['username']) || empty($_POST['password'])) {
            header("Location: login.php?error=empty");
            exit;
        }

        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Verifica si $pdo está definido
        if (!isset($pdo)) {
            throw new Exception("Error de conexión a la base de datos");
        }

        $stmt = $pdo->prepare("SELECT id, username, password, rol FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_rol'] = $user['rol'];
            $_SESSION['username'] = $user['username'];
            
            header("Location: ../index.php");
            exit;
        } else {
            header("Location: login.php?error=credentials");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error de base de datos: " . $e->getMessage());
        header("Location: login.php?error=db_error");
        exit;
    } catch (Exception $e) {
        error_log("Error general: " . $e->getMessage());
        header("Location: login.php?error=connection");
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, password, rol FROM usuarios WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

// Verificación segura con password_verify()
if ($user && password_verify($password, $user['password'])) {
    // Login exitoso
}
} else {
    header("Location: login.php");
    exit;
}
?>