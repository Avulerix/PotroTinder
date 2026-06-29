<?php
// Api/like.php - Procesar like con validación de bloqueo
session_start();
require_once '../Required/conect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

$usuario_id = $_SESSION['user_id'];
$liked_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verificar que el usuario actual NO esté bloqueado
$stmt_check = $conn->prepare("SELECT estado FROM usuarios WHERE id = ?");
$stmt_check->bind_param("i", $usuario_id);
$stmt_check->execute();
$user_status = $stmt_check->get_result()->fetch_assoc();

if ($user_status['estado'] === 'bloqueado') {
    header('Location: ../principal.php?error=bloqueado');
    exit;
}

if ($liked_id <= 0 || $liked_id == $usuario_id) {
    header('Location: ../principal.php?error=like_invalido');
    exit;
}

// Verificar que el usuario destino existe y está activo
$stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE id = ? AND estado = 'activo'");
$stmt_check->bind_param("i", $liked_id);
$stmt_check->execute();
$result = $stmt_check->get_result();
if ($result->num_rows === 0) {
    header('Location: ../principal.php?error=usuario_no_existe');
    exit;
}

// Insertar like (ignorar si ya existe)
$stmt_like = $conn->prepare("INSERT IGNORE INTO likes (usuario_id, liked_usuario_id) VALUES (?, ?)");
$stmt_like->bind_param("ii", $usuario_id, $liked_id);
$stmt_like->execute();

$es_match = false;

// Verificar si el otro usuario ya me había dado like (match mutuo)
$stmt_match_check = $conn->prepare("SELECT 1 FROM likes WHERE usuario_id = ? AND liked_usuario_id = ?");
$stmt_match_check->bind_param("ii", $liked_id, $usuario_id);
$stmt_match_check->execute();
if ($stmt_match_check->get_result()->num_rows > 0) {
    // Es match mutuo: insertar en tabla matches
    $stmt_match = $conn->prepare("INSERT IGNORE INTO matches (usuario1_id, usuario2_id) VALUES (?, ?)");
    $stmt_match->bind_param("ii", $usuario_id, $liked_id);
    $stmt_match->execute();
    $es_match = true;
}

// Redirigir de vuelta a la galería o al perfil
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../principal.php';
header("Location: $redirect_url" . ($es_match ? "?match=ok" : ""));
exit;
?>