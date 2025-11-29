<?php
/**
 * Funciones para manejo de notificaciones
 */

// Crear notificación
function crearNotificacion($usuario_id, $titulo, $mensaje, $tipo = 'info', $enlace = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $tipo, $titulo, $mensaje, $enlace]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al crear notificación: " . $e->getMessage());
        return false;
    }
}

// Obtener notificaciones no leídas
function obtenerNotificacionesNoLeidas($usuario_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM notificaciones 
            WHERE usuario_id = ? AND leida = 0 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$usuario_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error al obtener notificaciones: " . $e->getMessage());
        return [];
    }
}

// Marcar notificación como leída
function marcarNotificacionLeida($notificacion_id, $usuario_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE notificaciones 
            SET leida = 1 
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([$notificacion_id, $usuario_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al marcar notificación: " . $e->getMessage());
        return false;
    }
}

// Marcar todas las notificaciones como leídas
function marcarTodasLeidas($usuario_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE notificaciones 
            SET leida = 1 
            WHERE usuario_id = ? AND leida = 0
        ");
        $stmt->execute([$usuario_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al marcar notificaciones: " . $e->getMessage());
        return false;
    }
}

// Contar notificaciones no leídas
function contarNotificacionesNoLeidas($usuario_id) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total FROM notificaciones 
            WHERE usuario_id = ? AND leida = 0
        ");
        $stmt->execute([$usuario_id]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error al contar notificaciones: " . $e->getMessage());
        return 0;
    }
}

