<?php
//Arranque De Session
	session_start();

//Conexion A Base De Datos
	require_once '../Required/conect.php';

//Validar Que La Session Este Activa
	if (!isset($_SESSION['user_id'])) {
	    header('Location: login.html');
	    exit;
	}

//Generacion De Variables
	$usuario_id = $_SESSION['user_id'];
	$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

//Validar Si El ID Del Match Es nulo
	if ($match_id <= 0) {
	    header('Location: matches.php');
	    exit;
	}

// Eliminar el match (En Ambos Sentidos (A-B) & (B-A) )
	$stmt = $conn->prepare("DELETE FROM matches WHERE (usuario1_id = ? AND usuario2_id = ?) OR (usuario1_id = ? AND usuario2_id = ?)");
	$stmt->bind_param("iiii", $usuario_id, $match_id, $match_id, $usuario_id);
	$stmt->execute();

//Aqui Falta Descomentar Para Eliminar Los Likes (Para Limpiar Completamente) //// REVISAR ////
/*
	$stmt_ld = $conn->prepare("DELETE FROM likes WHERE (usuario1_id = ? AND liked_usuario_id = ?) OR (liked_usuario_id = ? AND usuario_id = ?)");
	$stmt_ld->bind_param("iiii", $usuario_id, $match_id, $match_id, $usuario_id);
	$stmt_ld->execute();

	
*/

//Retorno Final A La Pagina Anterior
	header('Location: ../matches.php');
	exit;
?>