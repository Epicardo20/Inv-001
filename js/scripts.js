// Variables globales
let medicines = [];
let currentUser = null;
let currentMedicineId = null;
let confirmCallback = null;

// Filtros
let filters = {
    searchTerm: '',
    status: '',
    nonExpired: false,
    lowStock: false,
    critical: false
};

// Inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Verificar autenticación al cargar
    checkAuth();
    
    // Configurar event listeners
    setupEventListeners();
    
    // Cargar datos iniciales si está autenticado
    if (currentUser) {
        loadMedicines();
        loadStats();
    }
});

// ======================
// FUNCIONES PRINCIPALES
// ======================

/**
 * Verifica el estado de autenticación del usuario
 */
function checkAuth() {
    // Simulación de autenticación - en un sistema real esto vendría del servidor
    const user = sessionStorage.getItem('pharma_user') || localStorage.getItem('pharma_user');
    if (user) {
        currentUser = JSON.parse(user);
        toggleAuthUI(true);
    } else {
        toggleAuthUI(false);
    }
}

/**
 * Configura todos los event listeners necesarios
 */
function setupEventListeners() {
    // Búsqueda y filtros
    document.getElementById('search-medicine').addEventListener('input', function(e) {
        filters.searchTerm = e.target.value.toLowerCase();
        filterMedicines();
    });
    
    document.getElementById('filter-status').addEventListener('change', function(e) {
        filters.status = e.target.value;
        filterMedicines();
    });
    
    // Botones de autenticación
    const loginBtn = document.getElementById('login-btn');
    if (loginBtn) {
        loginBtn.addEventListener('click', openLoginModal);
    }
    
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', handleLogout);
    }
    
    // Formularios
    const medicineForm = document.getElementById('medicine-form');
    if (medicineForm) {
        medicineForm.addEventListener('submit', handleMedicineSubmit);
    }
    
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // Botones de acción
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-edit')) {
            const id = e.target.getAttribute('data-id');
            openEditModal(id);
        }
        
        if (e.target.classList.contains('btn-delete')) {
            const id = e.target.getAttribute('data-id');
            const name = e.target.getAttribute('data-name');
            confirmDelete(id, name);
        }
    });
}

// ======================
// FUNCIONES DE AUTENTICACIÓN
// ======================

/**
 * Maneja el inicio de sesión
 */
function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;
    
    // Validación simple
    if (!username || !password) {
        alert('Por favor ingrese usuario y contraseña');
        return;
    }
    
    // Simulación de autenticación - en un sistema real harías una petición al servidor
    currentUser = {
        id: 1,
        username: username,
        role: 'admin' // o 'user' según corresponda
    };
    
    // Guardar en sessionStorage (sesión) o localStorage (persistente)
    sessionStorage.setItem('pharma_user', JSON.stringify(currentUser));
    
    // Actualizar UI
    toggleAuthUI(true);
    closeModal('login-modal');
    
    // Cargar datos
    loadMedicines();
    loadStats();
}

/**
 * Maneja el cierre de sesión
 */
function handleLogout() {
    // Limpiar datos de usuario
    sessionStorage.removeItem('pharma_user');
    localStorage.removeItem('pharma_user');
    currentUser = null;
    
    // Actualizar UI
    toggleAuthUI(false);
    
    // Limpiar datos sensibles
    medicines = [];
    document.getElementById('inventory-body').innerHTML = '';
    
    // Mostrar mensaje de login
    document.querySelector('.login-message').style.display = 'block';
}

/**
 * Alterna la interfaz de usuario según el estado de autenticación
 */
function toggleAuthUI(isAuthenticated) {
    const authElements = document.querySelectorAll('.auth-only');
    const unauthElements = document.querySelectorAll('.unauth-only');
    
    if (isAuthenticated) {
        authElements.forEach(el => el.style.display = '');
        unauthElements.forEach(el => el.style.display = 'none');
        document.querySelector('.login-message').style.display = 'none';
    } else {
        authElements.forEach(el => el.style.display = 'none');
        unauthElements.forEach(el => el.style.display = '');
    }
}

// ======================
// FUNCIONES DE MEDICAMENTOS
// ======================

/**
 * Carga los medicamentos desde el servidor
 */
async function loadMedicines() {
    try {
        // Mostrar carga
        const loader = document.createElement('div');
        loader.className = 'loader';
        const inventoryBody = document.getElementById('inventory-body');
        inventoryBody.innerHTML = '';
        inventoryBody.appendChild(loader);
        
        // Hacer la petición
        const response = await fetch('/InventarioF/php/crud.php');
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Verificar estructura de datos
        if (!Array.isArray(data)) {
            throw new Error('La respuesta no es un array de medicamentos');
        }
        
        medicines = data;
        renderMedicines();
        filterMedicines();
    } catch (error) {
        console.error('Error al cargar medicamentos:', error);
        alert('Error al cargar medicamentos. Consulte la consola para más detalles.');
    }
}

/**
 * Renderiza los medicamentos en la tabla
 */
function renderMedicines() {
    const tbody = document.getElementById('inventory-body');
    tbody.innerHTML = '';
    
    medicines.forEach(med => {
        const status = getMedicineStatus(med.caducidad);
        const statusText = getStatusText(status);
        const statusClass = getStatusClass(status);
        const stockClass = getStockClass(med.stock);
        
        const row = document.createElement('tr');
        row.setAttribute('data-id', med.id);
        row.setAttribute('data-status', status);
        row.setAttribute('data-stock', med.stock);
        
        row.innerHTML = `
            <td>${escapeHtml(med.nombre)}</td>
            <td>${escapeHtml(med.presentacion)}</td>
            <td>${escapeHtml(med.lote || 'N/A')}</td>
            <td><span class="stock-badge ${stockClass}">${med.stock} unidades</span></td>
            <td>${formatDate(med.caducidad)}</td>
            <td>${escapeHtml(med.proveedor || 'N/A')}</td>
            <td>${escapeHtml(med.receta || 'N/A')}</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            ${currentUser && currentUser.role === 'admin' ? `
            <td class="action-buttons">
                <button class="btn-icon edit btn-edit" data-id="${med.id}">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon delete btn-delete" data-id="${med.id}" data-name="${escapeHtml(med.nombre)}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
            ` : ''}
        `;
        
        tbody.appendChild(row);
    });
}

/**
 * Filtra los medicamentos según los criterios activos
 */
function filterMedicines() {
    const rows = document.querySelectorAll('#inventory-body tr');
    
    rows.forEach(row => {
        const name = row.cells[0].textContent.toLowerCase();
        const status = row.getAttribute('data-status');
        const stock = parseInt(row.getAttribute('data-stock'));
        
        // Aplicar filtros
        const matchesSearch = filters.searchTerm === '' || 
                             name.includes(filters.searchTerm);
        
        const matchesStatus = filters.status === '' || 
                             status === filters.status;
        
        const matchesNonExpired = !filters.nonExpired || 
                                status !== 'expired';
        
        const matchesLowStock = !filters.lowStock || 
                              stock < 20;
        
        const matchesCritical = !filters.critical || 
                              (status === 'expired' || stock < 10);
        
        // Mostrar u ocultar fila según los filtros
        row.style.display = matchesSearch && matchesStatus && 
                          matchesNonExpired && matchesLowStock && 
                          matchesCritical ? '' : 'none';
    });
}

// ======================
// FUNCIONES DE MODALES
// ======================

/**
 * Abre el modal para agregar un nuevo medicamento
 */
function openAddModal() {
    document.getElementById('modal-title').textContent = 'Agregar Medicamento';
    document.getElementById('medicine-form').reset();
    document.getElementById('medicine-id').value = '';
    openModal('medicine-modal');
}

/**
 * Abre el modal para editar un medicamento existente
 */
async function openEditModal(id) {
    try {
        // Mostrar loader
        const modalContent = document.querySelector('#medicine-modal .modal-content');
        const originalContent = modalContent.innerHTML;
        modalContent.innerHTML = '<div class="loader"></div>';
        openModal('medicine-modal');
        
        // Obtener datos del medicamento
        const response = await fetch(`/InventarioF/php/crud.php?id=${id}`);
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const medicine = await response.json();
        
        // Verificar datos
        if (!medicine || !medicine.id) {
            throw new Error('Datos de medicamento no válidos');
        }
        
        // Restaurar contenido y llenar formulario
        modalContent.innerHTML = originalContent;
        document.getElementById('modal-title').textContent = 'Editar Medicamento';
        
        // Formatear fecha para input type="date"
        const formatDateForInput = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        
        // Llenar formulario
        document.getElementById('medicine-id').value = medicine.id;
        document.getElementById('medicine-name').value = medicine.nombre || '';
        document.getElementById('medicine-presentation').value = medicine.presentacion || '';
        document.getElementById('medicine-lot').value = medicine.lote || '';
        document.getElementById('medicine-stock').value = medicine.stock || '';
        document.getElementById('medicine-expiry').value = formatDateForInput(medicine.caducidad);
        document.getElementById('medicine-provider').value = medicine.proveedor || '';
        document.getElementById('medicine-prescription').value = medicine.receta || '';
        document.getElementById('medicine-category').value = medicine.categoria || '';
        document.getElementById('medicine-price').value = medicine.precio || '';
        
    } catch (error) {
        console.error('Error al cargar medicamento:', error);
        closeModal('medicine-modal');
        alert('Error al cargar los datos del medicamento. Consulte la consola para más detalles.');
    }
}

/**
 * Maneja el envío del formulario de medicamentos
 */
async function handleMedicineSubmit(e) {
    e.preventDefault();
    
    // Obtener datos del formulario
    const formData = {
        id: document.getElementById('medicine-id').value,
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
    
    // Validación básica
    if (!formData.nombre || !formData.presentacion || !formData.stock || !formData.caducidad) {
        alert('Por favor complete los campos obligatorios: Nombre, Presentación, Stock y Caducidad');
        return;
    }
    
    try {
        // Configurar la petición
        const isEdit = !!formData.id;
        const url = `/InventarioF/php/crud.php${isEdit ? `?id=${formData.id}` : ''}`;
        const method = isEdit ? 'PUT' : 'POST';
        
        // Mostrar carga
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        submitBtn.disabled = true;
        
        // Enviar datos
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Error al guardar los datos');
        }
        
        // Cerrar modal y recargar datos
        closeModal('medicine-modal');
        await loadMedicines();
        
        // Mostrar feedback
        alert(`Medicamento ${isEdit ? 'actualizado' : 'agregado'} correctamente`);
        
    } catch (error) {
        console.error('Error al guardar:', error);
        alert(`Error al guardar: ${error.message}`);
    } finally {
        // Restaurar botón
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        }
    }
}

/**
 * Confirma la eliminación de un medicamento
 */
function confirmDelete(id, name) {
    currentMedicineId = id;
    
    document.getElementById('confirm-message').innerHTML = `
        ¿Está seguro que desea eliminar el medicamento <strong>${escapeHtml(name)}</strong>?
        <br><br>Esta acción no se puede deshacer.
    `;
    
    document.getElementById('confirm-action-button').onclick = function() {
        deleteMedicine(id);
    };
    
    openModal('confirm-modal');
}

/**
 * Elimina un medicamento
 */
async function deleteMedicine(id) {
    try {
        // Mostrar carga
        const confirmBtn = document.getElementById('confirm-action-button');
        const originalBtnText = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
        confirmBtn.disabled = true;
        
        // Enviar petición
        const response = await fetch(`/InventarioF/php/crud.php?id=${id}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Error al eliminar');
        }
        
        // Cerrar modal y recargar datos
        closeModal('confirm-modal');
        await loadMedicines();
        
        // Mostrar feedback
        alert('Medicamento eliminado correctamente');
        
    } catch (error) {
        console.error('Error al eliminar:', error);
        alert(`Error al eliminar: ${error.message}`);
    } finally {
        // Restaurar botón
        const confirmBtn = document.getElementById('confirm-action-button');
        if (confirmBtn) {
            confirmBtn.innerHTML = originalBtnText;
            confirmBtn.disabled = false;
        }
    }
}

// ======================
// FUNCIONES AUXILIARES
// ======================

/**
 * Abre un modal por su ID
 */
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Evitar scroll del body
}

/**
 * Cierra un modal por su ID
 */
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = ''; // Restaurar scroll del body
}

/**
 * Determina el estado de un medicamento según su fecha de caducidad
 */
function getMedicineStatus(expiryDate) {
    const now = new Date();
    const expiry = new Date(expiryDate);
    const oneMonthFromNow = new Date();
    oneMonthFromNow.setMonth(oneMonthFromNow.getMonth() + 1);
    
    if (expiry > oneMonthFromNow) return 'active';
    if (expiry > now) return 'warning';
    return 'expired';
}

/**
 * Obtiene el texto descriptivo para un estado
 */
function getStatusText(status) {
    const statusMap = {
        'active': 'Activo',
        'warning': 'Por caducar',
        'expired': 'Caducado'
    };
    return statusMap[status] || status;
}

/**
 * Obtiene la clase CSS para un estado
 */
function getStatusClass(status) {
    const classMap = {
        'active': 'active',
        'warning': 'warning',
        'expired': 'expired'
    };
    return classMap[status] || '';
}

/**
 * Obtiene la clase CSS para el stock
 */
function getStockClass(stock) {
    if (stock < 10) return 'low';
    if (stock < 20) return 'medium';
    return 'high';
}

/**
 * Formatea una fecha legible
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-ES', options);
}

/**
 * Escapa HTML para prevenir XSS
 */
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/**
 * Carga estadísticas desde el servidor
 */
async function loadStats() {
    try {
        const response = await fetch('/InventarioF/php/crud.php?stats=true');
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const stats = await response.json();
        renderStats(stats);
    } catch (error) {
        console.error('Error al cargar estadísticas:', error);
    }
}

/**
 * Renderiza las estadísticas
 */
function renderStats(stats) {
    const statsContainer = document.getElementById('stats-container');
    if (!statsContainer) return;
    
    statsContainer.innerHTML = `
        <div class="stat-card">
            <div class="stat-title">Total Medicamentos</div>
            <div class="stat-value">${stats.total || 0}</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Activos</div>
            <div class="stat-value stat-positive">${stats.active || 0}</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Por Caducar</div>
            <div class="stat-value stat-warning">${stats.warning || 0}</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Caducados</div>
            <div class="stat-value stat-negative">${stats.expired || 0}</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Bajo Stock</div>
            <div class="stat-value stat-negative">${stats.lowStock || 0}</div>
        </div>
    `;
}

/**
 * Muestra alertas de medicamentos próximos a caducar o con stock bajo
 */
function showExpiryAlerts() {
    const now = new Date();
    const oneMonthLater = new Date();
    oneMonthLater.setMonth(oneMonthLater.getMonth() + 1);
    
    const expiringSoon = medicines.filter(med => {
        const expiry = new Date(med.caducidad);
        return expiry > now && expiry <= oneMonthLater;
    });
    
    const lowStock = medicines.filter(med => med.stock < 10);
    const expired = medicines.filter(med => new Date(med.caducidad) <= now);
    
    let alertMessage = '';
    
    if (expired.length > 0) {
        alertMessage += `🚨 <strong>${expired.length} Medicamentos Caducados:</strong>\n`;
        expired.forEach(med => {
            alertMessage += `- ${med.nombre} (Caducidad: ${formatDate(med.caducidad)}, Stock: ${med.stock})\n`;
        });
        alertMessage += '\n';
    }
    
    if (expiringSoon.length > 0) {
        alertMessage += `⚠️ <strong>${expiringSoon.length} Medicamentos por Caducar:</strong>\n`;
        expiringSoon.forEach(med => {
            alertMessage += `- ${med.nombre} (Caducidad: ${formatDate(med.caducidad)}, Stock: ${med.stock})\n`;
        });
        alertMessage += '\n';
    }
    
    if (lowStock.length > 0) {
        alertMessage += `📉 <strong>${lowStock.length} Medicamentos con Stock Bajo (<10):</strong>\n`;
        lowStock.forEach(med => {
            alertMessage += `- ${med.nombre} (Stock: ${med.stock})\n`;
        });
    }
    
    if (alertMessage) {
        // Crear un modal personalizado para las alertas
        const alertModal = document.createElement('div');
        alertModal.className = 'modal';
        alertModal.id = 'alert-modal';
        alertModal.innerHTML = `
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h2>Alertas del Sistema</h2>
                    <span class="close" onclick="closeModal('alert-modal')">&times;</span>
                </div>
                <div class="modal-body" style="white-space: pre-line;">${alertMessage}</div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="closeModal('alert-modal')">Cerrar</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(alertModal);
        openModal('alert-modal');
    } else {
        alert('✅ No hay alertas importantes en este momento.');
    }
}

// Cerrar modales haciendo clic fuera del contenido
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
});

// Cerrar modales con Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal[style*="display: flex"]');
        openModals.forEach(modal => closeModal(modal.id));
    }
});