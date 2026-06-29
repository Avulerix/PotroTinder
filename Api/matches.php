<?php
// matches.php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$id = $_SESSION['user_id'];
$sql = "SELECT 
    CASE WHEN m.usuario1_id = ? THEN u2.id ELSE u1.id END as match_id,
    CASE WHEN m.usuario1_id = ? THEN u2.nombre_completo ELSE u1.nombre_completo END as nombre,
    CASE WHEN m.usuario1_id = ? THEN u2.foto_perfil ELSE u1.foto_perfil END as foto
    FROM matches m
    JOIN usuarios u1 ON m.usuario1_id = u1.id
    JOIN usuarios u2 ON m.usuario2_id = u2.id
    WHERE m.usuario1_id = ? OR m.usuario2_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $id, $id, $id, $id, $id);
$stmt->execute();
$matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!-- Mostrar matches sin opción de chat -->