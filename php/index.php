<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/InventarioF/php/config.php';

$is_logged_in = isset($_SESSION['user_id']);
$is_admin = ($is_logged_in && ($_SESSION['user_rol'] ?? '') === 'admin');

// Obtener medicamentos de la base de datos
$medicamentos = [];
try {
    $stmt = $pdo->query("SELECT * FROM medicamentos");
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener medicamentos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Inventario Farmacéutico</title>
    <link rel="stylesheet" href="/InventarioF/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .login-message {
            background-color: #fff3cd;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            border-radius: 5px;
            font-size: 1.1em;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .btn-icon {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s;
        }
        
        .btn-icon:hover {
            transform: scale(1.05);
            opacity: 0.9;
        }
        
        .btn-icon.edit {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-icon.delete {
            background-color: #f44336;
            color: white;
        }
        
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .badge.active {
            background-color: #4CAF50;
            color: white;
        }
        
        .badge.warning {
            background-color: #FFC107;
            color: #000;
        }
        
        .badge.expired {
            background-color: #f44336;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            animation: modalFadeIn 0.3s;
            max-width: 600px;
            width: 90%;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        
        .btn-success {
            background-color: #4CAF50;
            color: white;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header" id="header">
            <div class="header-top">
                <h1>Inventario Farmacéutico</h1>
                <div class="header-buttons">
                    <?php if ($is_logged_in): ?>
                        <span class="welcome-msg">Bienvenido, <?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?></span>
                        <?php if ($is_admin): ?>
                            <button class="btn btn-primary" onclick="openAddModal()">
                                <i class="fas fa-plus"></i> Agregar Medicamento
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-warning" onclick="showExpiryAlerts()">
                            <i class="fas fa-exclamation-triangle"></i> Alertas
                        </button>
                        <button class="btn btn-danger" id="logout-btn" onclick="logout()">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </button>
                    <?php else: ?>
                        <button class="btn btn-primary" id="login-btn" onclick="openLoginModal()">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="search-container">
                <input type="text" class="search-bar" id="search-medicine" placeholder="Buscar medicamento...">
                <select class="search-bar" id="filter-status">
                    <option value="">Todos los estados</option>
                    <option value="active">Activos</option>
                    <option value="warning">Por caducar</option>
                    <option value="expired">Caducados</option>
                </select>
            </div>
        </header>

        <?php if (!$is_logged_in): ?>
            <div class="login-message">
                <p><i class="fas fa-lock"></i> Inicie sesión para acceder a todas las funciones del sistema</p>
            </div>
        <?php endif; ?>

        <!-- Tabla de inventario -->
        <div class="responsive-table">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Medicamento</th>
                        <th>Presentación</th>
                        <th>Lote</th>
                        <th>Stock</th>
                        <th>Caducidad</th>
                        <th>Proveedor</th>
                        <th>Receta</th>
                        <th>Precio</th>  <!-- Nueva columna -->
                        <th>Estado</th>
                        <?php if ($is_admin): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="inventory-body">
                    <?php foreach ($medicamentos as $med): ?>
                        <tr>
                            <td><?= htmlspecialchars($med['nombre']) ?></td>
                            <td><?= htmlspecialchars($med['presentacion']) ?></td>
                            <td><?= htmlspecialchars($med['lote'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($med['stock']) ?></td>
                            <td><?= htmlspecialchars($med['caducidad']) ?></td>
                            <td><?= htmlspecialchars($med['proveedor'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($med['receta'] ?? 'N/A') ?></td>
                            <td><?= isset($med['precio']) ? '$' . number_format($med['precio'], 2) : 'N/A' ?></td> 
                            <td>
                                <span class="badge <?= 
                                    (strtotime($med['caducidad']) > strtotime('+1 month')) ? 'active' : 
                                    ((strtotime($med['caducidad']) > time()) ? 'warning' : 'expired')
                                ?>">
                                    <?= 
                                        (strtotime($med['caducidad']) > strtotime('+1 month')) ? 'Activo' : 
                                        ((strtotime($med['caducidad']) > time()) ? 'Por caducar' : 'Caducado')
                                    ?>
                                </span>
                            </td>
                            <?php if ($is_admin): ?>
                                <td class="action-buttons">
                                    <button class="btn-icon edit" onclick="openEditModal(<?= $med['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon delete" onclick="confirmDelete(<?= $med['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para agregar/editar -->
    <div id="medicine-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-title">Agregar Medicamento</h2>
                <span class="close" onclick="closeModal('medicine-modal')">&times;</span>
            </div>
            <form id="medicine-form">
                <input type="hidden" id="medicine-id">
                <div class="form-group">
                    <label for="medicine-name">Nombre del Medicamento*</label>
                    <input type="text" class="form-control" id="medicine-name" required>
                </div>
                <div class="form-group">
                    <label for="medicine-presentation">Presentación*</label>
                    <input type="text" class="form-control" id="medicine-presentation" required>
                </div>
                <div class="form-group">
                    <label for="medicine-lot">Número de Lote</label>
                    <input type="text" class="form-control" id="medicine-lot">
                </div>
                <div class="form-group">
                    <label for="medicine-stock">Stock*</label>
                    <input type="number" class="form-control" id="medicine-stock" min="0" required>
                </div>
                <div class="form-group">
                    <label for="medicine-expiry">Fecha de Caducidad*</label>
                    <input type="date" class="form-control" id="medicine-expiry" required>
                </div>
                <div class="form-group">
                    <label for="medicine-provider">Proveedor</label>
                    <input type="text" class="form-control" id="medicine-provider">
                </div>
                <div class="form-group">
                    <label for="medicine-prescription">Código de Receta</label>
                    <input type="text" class="form-control" id="medicine-prescription">
                </div>
                <div class="form-group">
                    <label for="medicine-category">Categoría</label>
                    <input type="text" class="form-control" id="medicine-category">
                </div>
                <div class="form-group">
                    <label for="medicine-price">Precio Unitario</label>
                    <input type="number" step="0.01" class="form-control" id="medicine-price" min="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="closeModal('medicine-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Login -->
    <div id="login-modal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2 class="modal-title">Iniciar Sesión</h2>
                <span class="close" onclick="closeModal('login-modal')">&times;</span>
            </div>
            <form id="login-form" action="php/auth/authenticate.php" method="POST">
                <div class="form-group">
                    <label for="login-username">Usuario</label>
                    <input type="text" class="form-control" id="login-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Contraseña</label>
                    <input type="password" class="form-control" id="login-password" name="password" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="closeModal('login-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-success">Ingresar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de confirmación -->
    <div id="confirm-modal" class="modal">
        <div class="modal-content" style="width: 400px;">
            <div class="modal-header">
                <h2 class="modal-title">Confirmar acción</h2>
                <span class="close" onclick="closeModal('confirm-modal')">&times;</span>
            </div>
            <div class="modal-body">
                <p id="confirm-message">¿Estás seguro de que deseas eliminar este medicamento?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="closeModal('confirm-modal')">Cancelar</button>
                <button type="button" class="btn btn-success" id="confirm-action-button">Confirmar</button>
            </div>
        </div>
    </div>

   <!-- (Todo el código HTML anterior se mantiene igual hasta el script final) -->

<script>
        // Variables globales
        let currentMedicineId = null;
        let medicinesData = []; // Almacenaremos los datos de medicamentos aquí
        
        // Función para cargar los datos de medicamentos
        function loadMedicinesData() {
            const rows = document.querySelectorAll('#inventory-body tr');
            medicinesData = [];
            
            rows.forEach(row => {
                medicinesData.push({
                    id: row.getAttribute('data-id'),
                    nombre: row.cells[0].textContent.toLowerCase(),
                    presentacion: row.cells[1].textContent.toLowerCase(),
                    lote: row.cells[2].textContent.toLowerCase(),
                    stock: parseInt(row.cells[3].textContent),
                    caducidad: row.cells[4].textContent,
                    proveedor: row.cells[5].textContent.toLowerCase(),
                    receta: row.cells[6].textContent.toLowerCase(),
                    status: row.querySelector('.badge').classList.contains('active') ? 'active' : 
                           row.querySelector('.badge').classList.contains('warning') ? 'warning' : 'expired'
                });
            });
        }
        
        // Función para aplicar filtros y búsqueda
        function applyFilters() {
            const searchTerm = document.getElementById('search-medicine').value.toLowerCase();
            const statusFilter = document.getElementById('filter-status').value;
            
            const rows = document.querySelectorAll('#inventory-body tr');
            
            rows.forEach(row => {
                const rowData = medicinesData.find(m => m.id === row.getAttribute('data-id'));
                
                // Verificar coincidencia con búsqueda
                const matchesSearch = searchTerm === '' || 
                                     rowData.nombre.includes(searchTerm) || 
                                     rowData.presentacion.includes(searchTerm) || 
                                     rowData.lote.includes(searchTerm) || 
                                     rowData.proveedor.includes(searchTerm);
                
                // Verificar filtro de estado
                const matchesStatus = statusFilter === '' || rowData.status === statusFilter;
                
                // Mostrar u ocultar fila según los filtros
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }
        
        // Inicializar los datos y event listeners cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar atributo data-id a cada fila
            document.querySelectorAll('#inventory-body tr').forEach((row, index) => {
                row.setAttribute('data-id', index);
            });
            
            // Cargar datos de medicamentos
            loadMedicinesData();
            
            // Configurar event listeners para búsqueda y filtros
            document.getElementById('search-medicine').addEventListener('input', applyFilters);
            document.getElementById('filter-status').addEventListener('change', applyFilters);
        });

        // Función para logout
        function logout() {
            fetch('/InventarioF/php/auth/logout.php')
                .then(response => {
                    if (response.ok) {
                        window.location.href = '/InventarioF/php/auth/login.php';
                    } else {
                        alert('Error al cerrar sesión');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cerrar sesión');
                });
        }

        // Funciones para modales
        function openModal(id) {
            document.getElementById(id).style.display = 'block';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Agregar Medicamento';
            document.getElementById('medicine-form').reset();
            document.getElementById('medicine-id').value = '';
            openModal('medicine-modal');
        }
        
        async function openEditModal(id) {
            try {
                // Mostrar indicador de carga
                const editButton = event.target;
                const originalHtml = editButton.innerHTML;
                editButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                editButton.disabled = true;

                // Realizar la petición
                const response = await fetch(`/InventarioF/php/crud.php?id=${id}`);
                
                // Verificar si la respuesta es JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error(`Respuesta no JSON: ${text.substring(0, 100)}...`);
                }

                // Parsear JSON
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Error en la respuesta del servidor');
                }

                if (!data.data || !data.data.id) {
                    throw new Error('Estructura de datos inválida');
                }
                
                // Formatear fecha para input type="date"
                const formatDate = (dateStr) => {
                    if (!dateStr) return '';
                    const date = new Date(dateStr);
                    return date.toISOString().split('T')[0];
                };

                // Llenar el formulario
                document.getElementById('modal-title').textContent = 'Editar Medicamento';
                document.getElementById('medicine-id').value = data.data.id;
                document.getElementById('medicine-name').value = data.data.nombre;
                document.getElementById('medicine-presentation').value = data.data.presentacion;
                document.getElementById('medicine-lot').value = data.data.lote || '';
                document.getElementById('medicine-stock').value = data.data.stock;
                document.getElementById('medicine-expiry').value = formatDate(data.data.caducidad);
                document.getElementById('medicine-provider').value = data.data.proveedor || '';
                document.getElementById('medicine-prescription').value = data.data.receta || '';
                document.getElementById('medicine-category').value = data.data.categoria || '';
                document.getElementById('medicine-price').value = data.data.precio || '';
                
                openModal('medicine-modal');

            } catch (error) {
                console.error('Error en openEditModal:', error);
                alert(`Error al cargar medicamento: ${error.message}`);
            } finally {
                // Restaurar el botón
                const editButton = event.target;
                editButton.innerHTML = originalHtml;
                editButton.disabled = false;
            }
        }
        
        function confirmDelete(id) {
            currentMedicineId = id;
            document.getElementById('confirm-message').textContent = 
                '¿Estás seguro de que deseas eliminar este medicamento?';
            document.getElementById('confirm-action-button').onclick = deleteMedicine;
            openModal('confirm-modal');
        }
        
        function deleteMedicine() {
            if (!currentMedicineId) return;
            
            fetch(`/InventarioF/php/crud.php?id=${currentMedicineId}`, {
                method: 'DELETE'
            })
            .then(response => {
                if (response.ok) {
                    window.location.reload();
                } else {
                    throw new Error('Error en la respuesta del servidor');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar el medicamento');
            });
        }
        
        // Manejar envío del formulario
        document.getElementById('medicine-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const form = e.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            try {
                // Mostrar indicador de carga
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                
                const id = document.getElementById('medicine-id').value;
                const isEdit = !!id;
                
                // Preparar datos del formulario
                const formData = {
                    nombre: document.getElementById('medicine-name').value,
                    presentacion: document.getElementById('medicine-presentation').value,
                    lote: document.getElementById('medicine-lot').value,
                    stock: document.getElementById('medicine-stock').value,
                    caducidad: document.getElementById('medicine-expiry').value,
                    proveedor: document.getElementById('medicine-provider').value,
                    receta: document.getElementById('medicine-prescription').value,
                    categoria: document.getElementById('medicine-category').value,
                    precio: document.getElementById('medicine-price').value
                };
                
                // Configurar la petición
                const url = `/InventarioF/php/crud.php${isEdit ? `?id=${id}` : ''}`;
                const options = {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                };
                
                // Solución alternativa para servidores que no soportan PUT
                if (isEdit) {
                    options.headers['X-HTTP-Method-Override'] = 'PUT';
                }
                
                const response = await fetch(url, options);
                
                // Verificar si la respuesta es JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error(`Respuesta no JSON: ${text.substring(0, 100)}...`);
                }
                
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Error al guardar los cambios');
                }
                
                // Recargar la página para ver los cambios
                window.location.reload();
                
            } catch (error) {
                console.error('Error al guardar:', error);
                alert(`Error al guardar: ${error.message}`);
            } finally {
                // Restaurar el botón
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        });
        
        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        };

        // Función para mostrar alertas de caducidad
        function showExpiryAlerts() {
            const now = new Date();
            const oneMonthLater = new Date();
            oneMonthLater.setMonth(oneMonthLater.getMonth() + 1);
            
            const expiredMeds = [];
            const expiringMeds = [];
            const lowStockMeds = [];
            
            medicinesData.forEach(med => {
                const expiryDate = new Date(med.caducidad);
                
                if (expiryDate <= now) {
                    expiredMeds.push(med);
                } else if (expiryDate <= oneMonthLater) {
                    expiringMeds.push(med);
                }
                
                if (med.stock < 10) {
                    lowStockMeds.push(med);
                }
            });
            
            let alertMessage = '';
            
            if (expiredMeds.length > 0) {
                alertMessage += `🚨 ${expiredMeds.length} Medicamentos Caducados:\n`;
                expiredMeds.forEach(med => {
                    alertMessage += `- ${med.nombre} (Caduca: ${med.caducidad}, Stock: ${med.stock})\n`;
                });
                alertMessage += '\n';
            }
            
            if (expiringMeds.length > 0) {
                alertMessage += `⚠️ ${expiringMeds.length} Medicamentos por Caducar:\n`;
                expiringMeds.forEach(med => {
                    alertMessage += `- ${med.nombre} (Caduca: ${med.caducidad}, Stock: ${med.stock})\n`;
                });
                alertMessage += '\n';
            }
            
            if (lowStockMeds.length > 0) {
                alertMessage += `📉 ${lowStockMeds.length} Medicamentos con Stock Bajo (<10):\n`;
                lowStockMeds.forEach(med => {
                    alertMessage += `- ${med.nombre} (Stock: ${med.stock})\n`;
                });
            }
            
            if (alertMessage) {
                alert(alertMessage);
            } else {
                alert('✅ No hay alertas importantes en este momento.');
            }
        }
    </script>
</body>
</html>
