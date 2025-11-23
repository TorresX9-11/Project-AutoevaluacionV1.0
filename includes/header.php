<?php
// Incluir funciones de notificaciones si el usuario está autenticado
if (isset($_SESSION['usuario_id'])) {
    require_once BASE_PATH . 'includes/notificaciones.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo) ? $titulo . ' - ' : ''; ?>TEC-UCT Autoevaluación</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    
    <!-- Estilo inline para asegurar navbar fijo -->
    <style>
        nav.navbar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1030 !important;
            width: 100% !important;
        }
        body {
            padding-top: 56px !important;
        }
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/favicon.png">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php">
                <img src="<?php echo BASE_URL; ?>assets/img/logo-uct.png" alt="TEC-UCT" height="40" class="me-2">
                <span class="d-none d-md-inline">EvaluaTEC</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['usuario_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>index.php">
                                <i class="bi bi-house"></i> Inicio
                            </a>
                        </li>
                        <?php if ($_SESSION['usuario_tipo'] === 'estudiante'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>estudiante/autoevaluacion.php">
                                    <i class="bi bi-clipboard-check"></i> Autoevaluación
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>estudiante/historial.php">
                                    <i class="bi bi-clock-history"></i> Historial
                                </a>
                            </li>
                        <?php endif; ?>
                        <!-- Nota: Docentes y Admin Supremo usan sidebar, no dropdown en navbar -->
                        <?php if (isset($_SESSION['usuario_id'])): 
                            $notificaciones_count = contarNotificacionesNoLeidas($_SESSION['usuario_id']);
                            $notificaciones = obtenerNotificacionesNoLeidas($_SESSION['usuario_id']);
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="notificacionesDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-bell"></i>
                                <?php if ($notificaciones_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill"><?php echo $notificaciones_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" style="min-width: 300px; max-height: 400px; overflow-y: auto;">
                                <li class="dropdown-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Notificaciones</span>
                                        <?php if ($notificaciones_count > 0): ?>
                                            <button type="button" class="btn btn-sm btn-link p-0" onclick="marcarTodasLeidas()">
                                                Marcar todas como leídas
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </li>
                                <?php if (empty($notificaciones)): ?>
                                    <li><span class="dropdown-item-text text-muted">No hay notificaciones nuevas</span></li>
                                <?php else: ?>
                                    <?php foreach ($notificaciones as $notif): ?>
                                        <li>
                                            <a class="dropdown-item <?php echo !$notif['leida'] ? 'bg-light' : ''; ?>" 
                                               href="<?php echo $notif['enlace'] ? BASE_URL . $notif['enlace'] : '#'; ?>"
                                               onclick="marcarNotificacionLeida(<?php echo $notif['id']; ?>)">
                                                <div class="d-flex">
                                                    <div class="flex-shrink-0">
                                                        <i class="bi bi-<?php 
                                                            echo $notif['tipo'] === 'success' ? 'check-circle text-success' : 
                                                                ($notif['tipo'] === 'warning' ? 'exclamation-triangle text-warning' : 
                                                                ($notif['tipo'] === 'danger' ? 'x-circle text-danger' : 'info-circle text-info')); 
                                                        ?>"></i>
                                                    </div>
                                                    <div class="flex-grow-1 ms-2">
                                                        <div class="fw-bold"><?php echo htmlspecialchars($notif['titulo']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($notif['mensaje']); ?></small>
                                                        <div class="text-muted" style="font-size: 0.75rem;">
                                                            <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>perfil.php">Mi Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>logout.php">Cerrar Sesión</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>login.php">Iniciar Sesión</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <?php 
    // Determinar si mostrar sidebar (docentes, admin_supremo y estudiantes en desktop/tablet)
    $mostrar_sidebar = isset($_SESSION['usuario_tipo']) && 
                       in_array($_SESSION['usuario_tipo'], ['estudiante', 'docente', 'admin', 'admin_supremo']);
    ?>
    
    <div class="wrapper <?php echo $mostrar_sidebar ? 'with-sidebar' : ''; ?>" data-user-type="<?php echo $_SESSION['usuario_tipo'] ?? ''; ?>">
        <?php if ($mostrar_sidebar): ?>
            <!-- Sidebar -->
            <aside id="sidebar" class="sidebar" data-user-type="<?php echo $_SESSION['usuario_tipo'] ?? ''; ?>">
                <div class="sidebar-header">
                    <h5 class="mb-0 sidebar-title">
                        <i class="bi bi-menu-button-wide"></i> <span class="sidebar-text">Menú</span>
                    </h5>
                    <button class="btn btn-sm btn-link text-white" id="sidebarToggleBtn" title="Minimizar/Expandir">
                        <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
                    </button>
                    <button class="btn btn-sm btn-link text-white d-md-none" id="sidebarClose">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <nav class="sidebar-nav">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" 
                               href="<?php echo BASE_URL; ?>index.php">
                                <i class="bi bi-house"></i> <span class="sidebar-text">Inicio</span>
                            </a>
                        </li>
                        
                        <?php if ($_SESSION['usuario_tipo'] === 'estudiante'): ?>
                            <li class="nav-item mt-3">
                                <span class="nav-link text-muted small text-uppercase fw-bold">
                                    <i class="bi bi-clipboard-check"></i> <span class="sidebar-text">Autoevaluación</span>
                                </span>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'estudiante/autoevaluacion') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>estudiante/autoevaluacion.php">
                                    <i class="bi bi-clipboard-check"></i> <span class="sidebar-text">Autoevaluación</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'estudiante/historial') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>estudiante/historial.php">
                                    <i class="bi bi-clock-history"></i> <span class="sidebar-text">Historial</span>
                                </a>
                            </li>
                        <?php elseif ($_SESSION['usuario_tipo'] === 'docente' || $_SESSION['usuario_tipo'] === 'admin'): ?>
                            <li class="nav-item mt-3">
                                <span class="nav-link text-muted small text-uppercase fw-bold">
                                    <i class="bi bi-gear"></i> <span class="sidebar-text">Administración</span>
                                </span>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'admin/rubricas') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>admin/rubricas.php">
                                    <i class="bi bi-clipboard-check"></i> <span class="sidebar-text">Rúbricas</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'admin/asignaturas') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>admin/asignaturas.php">
                                    <i class="bi bi-book"></i> <span class="sidebar-text">Asignaturas</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'admin/estudiantes') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>admin/estudiantes.php">
                                    <i class="bi bi-people"></i> <span class="sidebar-text">Estudiantes</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'admin/validaciones') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>admin/validaciones.php">
                                    <i class="bi bi-check-circle"></i> <span class="sidebar-text">Validaciones</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'admin/reportes') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>admin/reportes.php">
                                    <i class="bi bi-graph-up"></i> <span class="sidebar-text">Reportes</span>
                                </a>
                            </li>
                        <?php elseif ($_SESSION['usuario_tipo'] === 'admin_supremo'): ?>
                            <li class="nav-item mt-3">
                                <span class="nav-link text-muted small text-uppercase fw-bold">
                                    <i class="bi bi-shield-check"></i> <span class="sidebar-text">Administración Suprema</span>
                                </span>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'admin_supremo/carreras') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>admin_supremo/carreras.php">
                                    <i class="bi bi-mortarboard"></i> <span class="sidebar-text">Carreras</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'admin_supremo/docentes') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>admin_supremo/docentes.php">
                                    <i class="bi bi-person-badge"></i> <span class="sidebar-text">Docentes</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'admin_supremo/asignaturas') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>admin_supremo/asignaturas.php">
                                    <i class="bi bi-book"></i> <span class="sidebar-text">Asignaturas</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'admin_supremo/estudiantes') !== false) ? 'active' : ''; ?>" 
                                   href="<?php echo BASE_URL; ?>admin_supremo/estudiantes.php">
                                    <i class="bi bi-people"></i> <span class="sidebar-text">Estudiantes</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="nav-item mt-3">
                            <span class="nav-link text-muted small text-uppercase fw-bold">
                                <i class="bi bi-person"></i> <span class="sidebar-text">Cuenta</span>
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'perfil.php') ? 'active' : ''; ?>" 
                               href="<?php echo BASE_URL; ?>perfil.php">
                                <i class="bi bi-person-circle"></i> <span class="sidebar-text">Mi Perfil</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </aside>
            
            <!-- Botón para abrir sidebar en mobile -->
            <button class="btn btn-primary sidebar-toggle d-md-none" id="sidebarToggle" type="button">
                <i class="bi bi-list"></i>
            </button>
        <?php endif; ?>
        
        <!-- Contenido principal -->
        <main class="main-content <?php echo $mostrar_sidebar ? 'with-sidebar' : ''; ?>">
    
    <script>
    function marcarNotificacionLeida(notificacionId) {
        fetch('<?php echo BASE_URL; ?>ajax/marcar_notificacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + notificacionId
        }).then(() => {
            location.reload();
        });
    }
    
    function marcarTodasLeidas() {
        fetch('<?php echo BASE_URL; ?>ajax/marcar_todas_notificaciones.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        }).then(() => {
            location.reload();
        });
    }
    
    <?php if ($mostrar_sidebar): ?>
    // Sidebar functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
        const mainContent = document.querySelector('.main-content.with-sidebar');
        
        // Estado del sidebar (guardado en localStorage)
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed && sidebar) {
            sidebar.classList.add('collapsed');
            if (mainContent) {
                mainContent.classList.add('sidebar-collapsed');
            }
        }
        
        // Crear overlay para mobile
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        // Toggle minimizar/expandir (desktop)
        if (sidebarToggleBtn) {
            sidebarToggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('collapsed');
                if (mainContent) {
                    mainContent.classList.toggle('sidebar-collapsed');
                }
                // Guardar estado
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }
        
        // Abrir sidebar (mobile)
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            });
        }
        
        // Cerrar sidebar (mobile)
        if (sidebarClose) {
            sidebarClose.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
        
        // Cerrar al hacer clic en overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
        
        // Cerrar al hacer clic en un enlace del sidebar (mobile)
        if (window.innerWidth < 768) {
            const sidebarLinks = sidebar.querySelectorAll('.nav-link');
            sidebarLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                });
            });
        }
        
        // Ajustar contenido al redimensionar
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
    });
    <?php endif; ?>
    </script>

