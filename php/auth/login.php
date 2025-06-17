<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$error = '';

// Verifica si hay parámetro de error en la URL
if (isset($_GET['error'])) {
    $error = match($_GET['error']) {
        'credentials' => 'Usuario o contraseña incorrectos',
        'empty' => 'Complete todos los campos',
        'db_error' => 'Error en la base de datos',
        'connection' => 'Error de conexión',
        default => 'Error al iniciar sesión'
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sistema Farmacéutico</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --danger: #f72585;
            --success: #4cc9f0;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeIn 0.5s ease-in-out;
            transform-style: preserve-3d;
            transition: all 0.3s;
        }
        
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .login-header {
            background: var(--primary);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header i {
            font-size: 3.5rem;
            margin-bottom: 15px;
            animation: bounce 2s infinite;
        }
        
        .login-header h2 {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .login-form {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .form-icon {
            position: absolute;
            left: 15px;
            top: 40px;
            color: #777;
            font-size: 1.2rem;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a56d4;
            transform: translateY(-2px);
        }
        
        .error-message {
            color: var(--danger);
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
            animation: shake 0.5s;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #777;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-pills"></i>
            <h2>INVENTARIO FARMACÉUTICO</h2>
        </div>
        
        <form class="login-form" action="authenticate.php" method="POST">
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Usuario</label>
                <i class="fas fa-user form-icon"></i>
                <input type="text" class="form-control" id="username" name="username" placeholder="Ingrese su usuario" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <i class="fas fa-lock form-icon"></i>
                <input type="password" class="form-control" id="password" name="password" placeholder="Ingrese su contraseña" required>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i> INGRESAR
            </button>
            
            <p class="footer-text">Sistema de gestión farmacéutica v2.0</p>
        </form>
    </div>
</body>
</html>