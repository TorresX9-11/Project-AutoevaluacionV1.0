// JavaScript principal para la plataforma de autoevaluación TEC-UCT

// Funciones de utilidad
function mostrarAlerta(mensaje, tipo = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${tipo} alert-dismissible fade show alert-floating`;
    alert.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

// Confirmación antes de eliminar
document.addEventListener('DOMContentLoaded', function() {
    const linksEliminar = document.querySelectorAll('a[onclick*="confirm"]');
    linksEliminar.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('¿Está seguro de realizar esta acción?')) {
                e.preventDefault();
            }
        });
    });
});

// Validación de formularios
function validarFormulario(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const required = form.querySelectorAll('[required]');
    let valido = true;
    
    required.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            valido = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return valido;
}

// Auto-guardar en formularios largos
function autoGuardar(formId, url, intervalo = 30000) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    setInterval(() => {
        const formData = new FormData(form);
        fetch(url, {
            method: 'POST',
            body: formData
        }).catch(err => {
            console.error('Error en auto-guardado:', err);
        });
    }, intervalo);
}

// Manejo de archivos CSV
function validarArchivoCSV(input) {
    const file = input.files[0];
    if (!file) return false;
    
    const extension = file.name.split('.').pop().toLowerCase();
    if (!['csv', 'xlsx', 'xls'].includes(extension)) {
        mostrarAlerta('Por favor, seleccione un archivo CSV o Excel válido', 'danger');
        input.value = '';
        return false;
    }
    
    if (file.size > 5 * 1024 * 1024) { // 5MB
        mostrarAlerta('El archivo es demasiado grande. Máximo 5MB', 'danger');
        input.value = '';
        return false;
    }
    
    return true;
}

// Formatear números
function formatearNumero(numero, decimales = 2) {
    return parseFloat(numero).toFixed(decimales);
}

// Exportar a CSV (cliente)
function exportarTablaACSV(tablaId, nombreArchivo) {
    const tabla = document.getElementById(tablaId);
    if (!tabla) return;
    
    let csv = '';
    const filas = tabla.querySelectorAll('tr');
    
    filas.forEach(fila => {
        const celdas = fila.querySelectorAll('th, td');
        const valores = Array.from(celdas).map(celda => {
            let texto = celda.innerText.trim();
            texto = texto.replace(/"/g, '""');
            return `"${texto}"`;
        });
        csv += valores.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', nombreArchivo || 'exportacion.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Cargar más datos (paginación)
function cargarMas(url, contenedorId, pagina = 1) {
    fetch(`${url}?pagina=${pagina}`)
        .then(response => response.json())
        .then(data => {
            const contenedor = document.getElementById(contenedorId);
            if (data.html) {
                contenedor.innerHTML += data.html;
            }
            if (data.mas) {
                // Mostrar botón de cargar más
            }
        })
        .catch(err => {
            console.error('Error al cargar más datos:', err);
        });
}

// Validar correo institucional
function validarCorreoInstitucional(email, tipo) {
    if (tipo === 'estudiante') {
        return email.endsWith('@alu.uct.cl');
    } else if (tipo === 'docente' || tipo === 'admin') {
        return email.endsWith('@uct.cl');
    }
    return false;
}

// Contador regresivo genérico
function iniciarContadorRegresivo(elementoId, tiempoInicial, callback) {
    let tiempo = tiempoInicial;
    const elemento = document.getElementById(elementoId);
    
    const intervalo = setInterval(() => {
        tiempo--;
        
        if (tiempo <= 0) {
            clearInterval(intervalo);
            if (callback) callback();
            return;
        }
        
        const minutos = Math.floor(tiempo / 60);
        const segundos = tiempo % 60;
        elemento.textContent = `${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;
    }, 1000);
    
    return intervalo;
}

// Prevenir navegación accidental
window.addEventListener('beforeunload', function(e) {
    const formulariosActivos = document.querySelectorAll('form[data-unsaved="true"]');
    if (formulariosActivos.length > 0) {
        e.preventDefault();
        e.returnValue = '';
        return '';
    }
});

// Marcar formularios como modificados
document.addEventListener('DOMContentLoaded', function() {
    const formularios = document.querySelectorAll('form');
    formularios.forEach(form => {
        form.addEventListener('change', function() {
            this.setAttribute('data-unsaved', 'true');
        });
        
        form.addEventListener('submit', function() {
            this.removeAttribute('data-unsaved');
        });
    });
});

