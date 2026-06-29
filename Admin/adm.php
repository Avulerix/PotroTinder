<?php
// ==============================================
// PANEL ADMINISTRATIVO UNIFICADO - POTROTINDER
// Integra: Dashboard (usuarios), Accesos (logs), Alertas
// ==============================================
session_start();
require_once '../Required/conect.php';

// Validación de sesión y rol admin
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    exit;
}

// Variable para la pestaña activa (users, logs, alerts)
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';
//
//
//
//
//
//
// ======================== SECCIÓN: USUARIOS ========================
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section']) && $_POST['section'] === 'users') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($_POST['accion'] === 'bloquear') {
        $nuevo_estado = 'bloqueado';
        $stmt = $conn->prepare("UPDATE usuarios SET estado = ?, motivo_bloqueo = ? WHERE id = ?");
        $motivo = $_POST['motivo_bloqueo'] ?? 'Infracción de normas';
        $stmt->bind_param("ssi", $nuevo_estado, $motivo, $user_id);
        $stmt->execute();
        $mensaje = "Usuario bloqueado correctamente.";
    } 
    elseif ($_POST['accion'] === 'activar') {
        $nuevo_estado = 'activo';
        $stmt = $conn->prepare("UPDATE usuarios SET estado = ?, motivo_bloqueo = NULL WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $user_id);
        $stmt->execute();
        $mensaje = "Usuario activado correctamente.";
    }
    elseif ($_POST['accion'] === 'validar_identidad') {
        $stmt = $conn->prepare("UPDATE usuarios SET identidad_verificada = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $mensaje = "Identidad del usuario validada correctamente.";
    }
    elseif ($_POST['accion'] === 'invalidar_identidad') {
        $stmt = $conn->prepare("UPDATE usuarios SET identidad_verificada = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $mensaje = "Identidad del usuario invalidada. Deberá volver a subir su documentación.";
    }
    header("Location: ?tab=users&msg=" . urlencode($mensaje));
    exit;
}

if (isset($_GET['msg'])) {
    $mensaje = htmlspecialchars($_GET['msg']);
}

// Estadísticas de usuarios
$stats = [];
$res = $conn->query("SELECT COUNT(*) as total FROM usuarios");
$stats['total'] = $res->fetch_assoc()['total'];
$res = $conn->query("SELECT COUNT(*) as verificados FROM usuarios WHERE identidad_verificada = 1");
$stats['verificados'] = $res->fetch_assoc()['verificados'];
$res = $conn->query("SELECT COUNT(*) as pendientes FROM usuarios WHERE identidad_verificada = 0 AND foto_identificacion IS NOT NULL");
$stats['pendientes'] = $res->fetch_assoc()['pendientes'];
$res = $conn->query("SELECT COUNT(*) as bloqueados FROM usuarios WHERE estado = 'bloqueado'");
$stats['bloqueados'] = $res->fetch_assoc()['bloqueados'];
$res = $conn->query("SELECT COUNT(*) as activos FROM usuarios WHERE estado = 'activo'");
$stats['activos'] = $res->fetch_assoc()['activos'];

// Lista de usuarios
$usuarios = [];
$sql_users = "SELECT id, nombre_completo, email, telefono, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad, ciudad, estado, rol, foto_perfil, foto_identificacion, identidad_verificada, motivo_bloqueo, created_at FROM usuarios ORDER BY id DESC";
$result = $conn->query($sql_users);
$usuarios = $result->fetch_all(MYSQLI_ASSOC);
//
//
//
//
//
// ======================== SECCIÓN: ACCESOS (LOGS) ========================
$registros_por_pagina = 200;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtro_fecha = isset($_GET['filtro_fecha']) ? $_GET['filtro_fecha'] : '';

$where_conditions = [];
$param_types = "";
$param_values = [];

if (!empty($busqueda)) {
    $where_conditions[] = "(usuario LIKE ? OR usr_ip LIKE ? OR navegador LIKE ? OR dispositivo LIKE ?)";
    $like_param = "%$busqueda%";
    $param_types .= "ssss";
    $param_values = array_merge($param_values, [$like_param, $like_param, $like_param, $like_param]);
}
if (!empty($filtro_fecha)) {
    $where_conditions[] = "DATE(momento) = ?";
    $param_types .= "s";
    $param_values[] = $filtro_fecha;
}
$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Total registros
$count_sql = "SELECT COUNT(*) as total FROM rastreo $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($param_values)) {
    $count_stmt->bind_param($param_types, ...$param_values);
}
$count_stmt->execute();
$total_registros = $count_stmt->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);
$count_stmt->close();

// Consulta principal
$sql = "SELECT acceso, navegador, dispositivo, usr_ip, usuario, usr_id, momento FROM rastreo $where_sql ORDER BY momento DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!empty($param_values)) {
    $main_param_types = $param_types . "ii";
    $main_param_values = array_merge($param_values, [$registros_por_pagina, $offset]);
    $stmt->bind_param($main_param_types, ...$main_param_values);
} else {
    $stmt->bind_param("ii", $registros_por_pagina, $offset);
}
$stmt->execute();
$accesos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Estadísticas de accesos
$stats_accesos = $conn->query("SELECT COUNT(*) as total_accesos, COUNT(DISTINCT usuario) as usuarios_unicos, COUNT(DISTINCT usr_ip) as ips_unicas, DATE(MIN(momento)) as primer_acceso, DATE(MAX(momento)) as ultimo_acceso FROM rastreo")->fetch_assoc();

// Navegadores más usados
$browsers = [];
$browser_res = $conn->query("SELECT CASE WHEN navegador LIKE '%Chrome%' THEN 'Chrome' WHEN navegador LIKE '%Firefox%' THEN 'Firefox' WHEN navegador LIKE '%Safari%' THEN 'Safari' WHEN navegador LIKE '%Edge%' THEN 'Edge' WHEN navegador LIKE '%Opera%' THEN 'Opera' ELSE 'Otros' END as browser_group, COUNT(*) as total FROM rastreo GROUP BY browser_group ORDER BY total DESC");
while ($row = $browser_res->fetch_assoc()) $browsers[] = $row;
//
//
//
//
//
//
//
// ======================== SECCIÓN: ALERTAS ========================
$es_admin = true; //Validacion de rol administrativo
$usuario_actual_id = $_SESSION['user_id'];
$perfil_id = isset($_GET['perfil_id']) ? (int)$_GET['perfil_id'] : 0;
$modo_gestion = !isset($_GET['ver_perfil']);

// Procesar acciones de alertas (CREAR y ELIMINAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section']) && $_POST['section'] === 'alerts') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear_alerta') {
        $titulo = trim($_POST['titulo']);
        $autor = trim($_POST['autor']) ?: 'Administrador';
        $usuario_id = (int)$_POST['usuario_id'];
        $descripcion = trim($_POST['descripcion']);
        if ($titulo && $usuario_id && $descripcion) {
            $stmt = $conn->prepare("INSERT INTO alertas (Titulo, Autor, Usuario_id, Descripcion) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $titulo, $autor, $usuario_id, $descripcion);
            if ($stmt->execute()) {
                $mensaje = "Alerta creada correctamente.";
            } else {
                $mensaje = "Error al crear alerta: " . $conn->error;
            }
            $stmt->close();
        } else {
            $mensaje = "Todos los campos son obligatorios.";
        }
        // Redirigir para evitar reenvío del formulario
        header("Location: ?tab=alerts&msg=" . urlencode($mensaje));
        exit;
    }
    
    elseif ($accion === 'eliminar_alerta') {
        $alerta_id = (int)($_POST['alerta_id'] ?? 0);
        if ($alerta_id > 0) {
            $stmt = $conn->prepare("DELETE FROM alertas WHERE ID = ?");
            $stmt->bind_param("i", $alerta_id);
            if ($stmt->execute()) {
                $mensaje = "Alerta eliminada correctamente.";
            } else {
                $mensaje = "Error al eliminar alerta: " . $conn->error;
            }
            $stmt->close();
        } else {
            $mensaje = "ID de alerta no válido.";
        }
        // Redirigir a la misma pestaña manteniendo el filtro si existe
        $redirect = "?tab=alerts";
        if (!$modo_gestion && $perfil_id > 0) {
            $redirect .= "&ver_perfil=$perfil_id";
        }
        header("Location: $redirect&msg=" . urlencode($mensaje));
        exit;
    }
}

// Recibir mensajes
if (isset($_GET['msg'])) {
    $mensaje = htmlspecialchars($_GET['msg']);
}

// Lista de usuarios para selector de alertas
$usuarios_lista = $conn->query("SELECT id, nombre_completo, email FROM usuarios ORDER BY nombre_completo")->fetch_all(MYSQLI_ASSOC);

// Alertas para el perfil seleccionado o todas (admin)
$alertas = [];
$todas_alertas = [];
if ($modo_gestion) {
    // Modo administrador: ver todas las alertas
    $res = $conn->query("
        SELECT a.ID, a.Titulo, a.Autor, a.Usuario_id, a.Descripcion, u.nombre_completo as usuario_nombre 
        FROM alertas a 
        LEFT JOIN usuarios u ON a.Usuario_id = u.id 
        ORDER BY a.ID DESC
    ");
    $todas_alertas = $res->fetch_all(MYSQLI_ASSOC);
} elseif ($perfil_id > 0) {
    // Ver alertas de un usuario específico
    $stmt = $conn->prepare("SELECT ID, Titulo, Autor, Descripcion FROM alertas WHERE Usuario_id = ? ORDER BY ID DESC");
    $stmt->bind_param("i", $perfil_id);
    $stmt->execute();
    $alertas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    // Obtener nombre del usuario
    $user_nombre = $conn->query("SELECT nombre_completo FROM usuarios WHERE id = $perfil_id")->fetch_assoc();
    $usuario_nombre = $user_nombre ? $user_nombre['nombre_completo'] : 'Usuario';
}
//
//
//
//
//
//
// Datos del admin para la barra de navegacion
$admin_data = $conn->query("SELECT nombre_completo, foto_perfil FROM usuarios WHERE id = {$_SESSION['user_id']}")->fetch_assoc();
?>
<!----CODIGO VISUAL---->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo| PotroTinder</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #fefaf7 0%, #f0f5ec 100%); min-height: 100vh; }
        .navbar { background: white; box-shadow: 0 2px 15px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; padding: 0.8rem 2rem; }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .logo a { font-size: 1.6rem; font-weight: 800; text-decoration: none; color: rgb(226,43,150); display: flex; align-items: center; gap: 8px; }
        .logo i { color: rgb(33,100,16); }
        .nav-links { display: flex; align-items: center; gap: 2rem; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; color: #4a5a40; font-weight: 500; transition: color 0.2s; display: flex; align-items: center; gap: 8px; }
        .nav-links a:hover, .nav-links a.active { color: rgb(226,43,150); }
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-btn { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .profile-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid rgb(226,43,150); }
        .dropdown-content { display: none; position: absolute; right: 0; background: white; min-width: 180px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-radius: 1rem; overflow: hidden; z-index: 200; }
        .dropdown-content a { display: block; padding: 0.8rem 1.2rem; text-decoration: none; color: #3a4634; }
        .dropdown-content a:hover { background: #f5f9f0; color: rgb(226,43,150); }
        .profile-dropdown:hover .dropdown-content { display: block; }
        .tabs { display: flex; gap: 0.5rem; background: white; border-radius: 3rem; padding: 0.3rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .tab-btn { flex: 1; text-align: center; padding: 0.8rem; border-radius: 2rem; font-weight: 600; background: transparent; border: none; cursor: pointer; transition: all 0.2s; font-size: 1rem; }
        .tab-btn i { margin-right: 8px; }
        .tab-btn.active { background: rgb(33,100,16); color: white; box-shadow: 0 4px 10px rgba(33,100,16,0.3); }
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 1.5rem; padding: 1.2rem; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .stat-card i { font-size: 2rem; color: rgb(226,43,150); margin-bottom: 0.5rem; }
        .stat-card h3 { font-size: 1.8rem; color: rgb(33,100,16); }
        .stat-card p { color: #6c7a64; font-weight: 500; }
        .table-container { background: white; border-radius: 1.5rem; overflow-x: auto; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.8rem 1rem; text-align: left; border-bottom: 1px solid #e2e8dc; }
        th { background: rgb(33,100,16); color: white; font-weight: 600; }
        tr:hover { background: #f8faf5; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-verificado { background: #d4edda; color: #155724; }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-activo { background: #d4edda; color: #155724; }
        .badge-bloqueado { background: #f8d7da; color: #721c24; }
        .btn-small { padding: 0.3rem 0.8rem; border-radius: 1rem; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 600; color: white; }
        .btn-bloquear { background: rgb(226,43,150); }
        .btn-activar { background: rgb(33,100,16); }
        .btn-validar { background: #28a745; }
        .btn-invalidar { background: #ffc107; color: #333; }
        .foto-mini { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .filters-bar { background: white; border-radius: 1.2rem; padding: 1rem; margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label { font-size: 0.7rem; font-weight: 600; color: #6c7a64; }
        .filter-group input, .filter-group select { width: 100%; padding: 0.5rem; border: 1px solid #e2e8dc; border-radius: 0.8rem; }
        .btn-filter, .btn-reset { padding: 0.5rem 1rem; border-radius: 2rem; border: none; font-weight: 600; cursor: pointer; }
        .btn-filter { background: rgb(33,100,16); color: white; }
        .btn-reset { background: #e2e8dc; color: #4a5c42; }
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 0.4rem 0.8rem; border-radius: 0.5rem; text-decoration: none; background: white; border: 1px solid #e2e8dc; color: #4a5c42; }
        .pagination a:hover, .pagination .active { background: rgb(33,100,16); color: white; }
        .admin-panel { background: white; border-radius: 1.5rem; padding: 1.8rem; margin-bottom: 2rem; box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .admin-panel h3 { color: rgb(33,100,16); margin-bottom: 1.5rem; font-size: 1.3rem; border-left: 4px solid rgb(226,43,150); padding-left: 1rem; }
        .form-alerta-modern { display: flex; flex-direction: column; gap: 1.2rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
        .form-field { display: flex; flex-direction: column; }
        .form-field.full-width { grid-column: span 2; }
        .form-field label { font-size: 0.8rem; font-weight: 600; color: #2c3a26; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 6px; }
        .form-field label i { color: rgb(226,43,150); width: 1.2rem; }
        .form-field input, .form-field select, .form-field textarea { padding: 0.7rem 1rem; border: 1.5px solid #e2e8dc; border-radius: 1rem; font-family: 'Inter', sans-serif; font-size: 0.9rem; background: #fefefc; }
        .form-field input:focus, .form-field select:focus, .form-field textarea:focus { outline: none; border-color: rgb(226,43,150); box-shadow: 0 0 0 3px rgba(226,43,150,0.1); background: white; }
        .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 0.5rem; }
        .btn-submit { background: rgb(33,100,16); color: white; border: none; padding: 0.7rem 1.5rem; border-radius: 2.5rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { background: #1f6e12; transform: translateY(-2px); box-shadow: 0 5px 12px rgba(33,100,16,0.3); }
        .btn-reset-form { background: #f0f2ef; color: #4a5c42; border: 1px solid #e2e8dc; padding: 0.7rem 1.5rem; border-radius: 2.5rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-reset-form:hover { background: #e4e8e0; transform: translateY(-1px); }
        .alertas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
        .alerta-card { background: white; border-radius: 1.2rem; border-left: 4px solid rgb(226,43,150); box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .alerta-header { padding: 1rem; background: #fefaf7; border-bottom: 1px solid #e2e8dc; }
        .alerta-titulo { font-weight: 700; color: rgb(33,100,16); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        .alerta-autor { font-size: 0.7rem; color: #8da082; margin-top: 0.3rem; display: flex; justify-content: space-between; flex-wrap: wrap; }
        .alerta-body { padding: 1rem; }
        .alerta-footer { padding: 0.5rem 1rem; background: #fafef7; text-align: right; }
        .selector-perfil { background: white; border-radius: 1rem; padding: 1rem; margin-bottom: 1rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .mensaje { background: #d4edda; color: #155724; padding: 0.8rem; border-radius: 1rem; margin-bottom: 1rem; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        @media (max-width: 768px) { .nav-container { flex-direction: column; } .tabs { flex-direction: column; } .tab-btn { width: 100%; } .form-row { grid-template-columns: 1fr; } .form-field.full-width { grid-column: span 1; } .form-actions { flex-direction: column; align-items: stretch; } .btn-submit, .btn-reset-form { justify-content: center; } }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <div class="logo"><a href="?tab=users"><i class="fas fa-heart"></i> PotroTinder | Admin</a></div>
        <div class="nav-links">
            <a href="../principal.php"><i class="fas fa-home"></i> Ver sitio</a>
            <div class="profile-dropdown">
                <div class="profile-btn">
                    <img src="../Uploads/profile/<?= htmlspecialchars($admin_data['foto_perfil'] ?? 'default_avatar.jpg') ?>" class="profile-img" onerror="this.src='https://randomuser.me/api/portraits/lego/1.jpg'">
                    <span><?= htmlspecialchars(explode(' ', $admin_data['nombre_completo'])[0]) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="dropdown-content">
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="container">
    <?php if ($mensaje): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <!-- Pestañas -->
    <div class="tabs">
        <button class="tab-btn <?= $tab === 'users' ? 'active' : '' ?>" onclick="switchTab('users')"><i class="fas fa-users"></i> Usuarios</button>
        <button class="tab-btn <?= $tab === 'logs' ? 'active' : '' ?>" onclick="switchTab('logs')"><i class="fas fa-chart-line"></i> Accesos (Logs)</button>
        <button class="tab-btn <?= $tab === 'alerts' ? 'active' : '' ?>" onclick="switchTab('alerts')"><i class="fas fa-bell"></i> Alertas</button>
    </div>

    <!-- Contenido: Usuarios -->
    <div id="usersTab" class="tab-content <?= $tab === 'users' ? 'active' : '' ?>">
        <div class="stats-grid">
            <div class="stat-card"><i class="fas fa-users"></i><h3><?= $stats['total'] ?></h3><p>Total usuarios</p></div>
            <div class="stat-card"><i class="fas fa-check-circle"></i><h3><?= $stats['verificados'] ?></h3><p>Identidad verificada</p></div>
            <div class="stat-card"><i class="fas fa-clock"></i><h3><?= $stats['pendientes'] ?></h3><p>Validación pendiente</p></div>
            <div class="stat-card"><i class="fas fa-ban"></i><h3><?= $stats['bloqueados'] ?></h3><p>Bloqueados</p></div>
            <div class="stat-card"><i class="fas fa-heart"></i><h3><?= $stats['activos'] ?></h3><p>Activos</p></div>
        </div>
        <div class="table-container">
            <table>
                <thead><tr><th>ID</th><th>Foto</th><th>Nombre</th><th>Email</th><th>Telefono</th><th>Edad</th><th>Ciudad</th><th>Estado</th><th>Verif.</th><th>ID Doc</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><img src="../Uploads/profile/<?= htmlspecialchars($u['foto_perfil'] ?? 'default_avatar.jpg') ?>" class="foto-mini" onerror="this.src='https://randomuser.me/api/portraits/lego/1.jpg'"></td>
                        <td><?= htmlspecialchars($u['nombre_completo']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['telefono']) ?></td>
                        <td><?= $u['edad'] ?></td>
                        <td><?= htmlspecialchars($u['ciudad'] ?: '—') ?></td>
                        <td><span class="badge <?= $u['estado'] === 'activo' ? 'badge-activo' : 'badge-bloqueado' ?>"><?= $u['estado'] === 'activo' ? 'Activo' : 'Bloqueado' ?></span></td>
                        <td><?= $u['identidad_verificada'] ? '<span class="badge badge-verificado"><i class="fas fa-check-circle"></i> Verificado</span>' : ($u['foto_identificacion'] ? '<span class="badge badge-pendiente">Pendiente</span>' : '<span class="badge">No</span>') ?></td>
                        <td><?= $u['foto_identificacion'] ? "<button class='ver-id' data-img='../Uploads/validate/{$u['foto_identificacion']}' style='background:none;border:none;color:rgb(226,43,150);cursor:pointer;'><i class='fas fa-id-card'></i> Ver</button>" : '—' ?></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <?php if ($u['foto_identificacion'] && !$u['identidad_verificada']): ?>
                                    <form method="POST"><input type="hidden" name="section" value="users"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="accion" value="validar_identidad"><button class="btn-small btn-validar"><i class="fas fa-check"></i> Validar</button></form>
                                <?php endif; ?>
                                <?php if ($u['identidad_verificada']): ?>
                                    <form method="POST" onsubmit="return confirm('¿Invalidar identidad?')"><input type="hidden" name="section" value="users"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="accion" value="invalidar_identidad"><button class="btn-small btn-invalidar"><i class="fas fa-times"></i> Invalidar</button></form>
                                <?php endif; ?>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <?php if ($u['estado'] === 'activo'): ?>
                                        <form method="POST" onsubmit="return confirm('¿Bloquear?')"><input type="hidden" name="section" value="users"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="accion" value="bloquear"><input type="text" name="motivo_bloqueo" placeholder="Motivo" style="width:90px;padding:0.2rem;"><button class="btn-small btn-bloquear">Bloquear</button></form>
                                    <?php else: ?>
                                        <form method="POST"><input type="hidden" name="section" value="users"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="accion" value="activar"><button class="btn-small btn-activar">Activar</button></form>
                                    <?php endif; ?>
                                <?php else: ?><span style="color:#ccc;">Tú</span><?php endif; ?>
                            </div>
                            <?php if ($u['motivo_bloqueo'] && $u['estado'] === 'bloqueado'): ?><div style="font-size:0.65rem; color:#999;"><?= htmlspecialchars($u['motivo_bloqueo']) ?></div><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Contenido: Accesos (Logs) -->
    <div id="logsTab" class="tab-content <?= $tab === 'logs' ? 'active' : '' ?>">
        <div class="stats-grid">
            <div class="stat-card"><i class="fas fa-door-open"></i><h3><?= number_format($stats_accesos['total_accesos']) ?></h3><p>Total accesos</p></div>
            <div class="stat-card"><i class="fas fa-users"></i><h3><?= number_format($stats_accesos['usuarios_unicos']) ?></h3><p>Usuarios únicos</p></div>
            <div class="stat-card"><i class="fas fa-network-wired"></i><h3><?= number_format($stats_accesos['ips_unicas']) ?></h3><p>IPs distintas</p></div>
            <div class="stat-card"><i class="fas fa-calendar"></i><h3><?= $stats_accesos['primer_acceso'] ? date('d/m/Y', strtotime($stats_accesos['primer_acceso'])) : 'N/A' ?></h3><p>Primer registro</p></div>
        </div>
        <div class="filters-bar">
            <div class="filter-group"><label><i class="fas fa-search"></i> Buscar</label><input type="text" id="searchInput" placeholder="Usuario, IP, navegador..." value="<?= htmlspecialchars($busqueda) ?>"></div>
            <div class="filter-group"><label><i class="fas fa-calendar-day"></i> Fecha</label><input type="date" id="fechaFilter" value="<?= htmlspecialchars($filtro_fecha) ?>"></div>
            <div class="filter-group"><button class="btn-filter" id="btnBuscar">Aplicar filtros</button><button class="btn-reset" id="btnReset">Limpiar</button></div>
        </div>
        <?php if(!empty($browsers)): ?>
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(120px,1fr)); margin-bottom:1rem;">
            <?php foreach($browsers as $b): ?>
            <div class="stat-card" style="padding:0.5rem;"><i class="fab fa-<?= strtolower($b['browser_group']) == 'chrome' ? 'chrome' : (strtolower($b['browser_group']) == 'firefox' ? 'firefox' : (strtolower($b['browser_group']) == 'safari' ? 'safari' : 'internet-explorer')) ?>"></i> <?= $b['browser_group'] ?> <strong><?= $b['total'] ?></strong></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="table-container">
            <table>
                <thead><tr><th>ID</th><th>Usuario</th><th>ID Usuario</th><th>Navegador</th><th>Dispositivo</th><th>IP</th><th>Momento</th></tr></thead>
                <tbody>
                <?php foreach($accesos as $a): ?>
                    <tr><td>#<?= $a['acceso'] ?></td>
                        <td><?= $a['usuario'] ? "<a href='#' class='user-link' onclick='filtrarUsuario(\"" . htmlspecialchars($a['usuario']) . "\")' style='color:rgb(226,43,150);'>" . htmlspecialchars($a['usuario']) . "</a>" : '<span class="badge">Anónimo</span>' ?></td>
                        <td><?= $a['usr_id'] ?: '—' ?></td>
                        <td><i class="fas fa-window-maximize"></i> <?= htmlspecialchars($a['navegador'] ?: 'No registrado') ?></td>
                        <td><?php $is_mobile = stripos($a['dispositivo'], 'mobile') !== false; ?><span class="badge <?= $is_mobile ? 'badge-pendiente' : 'badge-verificado' ?>"><i class="fas <?= $is_mobile ? 'fa-mobile-alt' : 'fa-desktop' ?>"></i> <?= htmlspecialchars(substr($a['dispositivo'] ?: 'Desconocido',0,40)) ?></span></td>
                        <td><span class="badge"><?= htmlspecialchars($a['usr_ip'] ?: '0.0.0.0') ?></span></td>
                        <td><?= date('d/m/Y H:i:s', strtotime($a['momento'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($accesos)): ?><tr><td colspan="7" class="alert-info">No se encontraron registros.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($total_paginas > 1): ?>
        <div class="pagination">
            <?php if($pagina_actual > 1): ?><a href="?tab=logs&pagina=1&busqueda=<?= urlencode($busqueda) ?>&filtro_fecha=<?= urlencode($filtro_fecha) ?>"><i class="fas fa-angle-double-left"></i></a><a href="?tab=logs&pagina=<?= $pagina_actual-1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_fecha=<?= urlencode($filtro_fecha) ?>"><i class="fas fa-angle-left"></i></a><?php endif; ?>
            <?php for($i=1;$i<=$total_paginas;$i++): if($i==1 || $i==$total_paginas || ($i>=$pagina_actual-2 && $i<=$pagina_actual+2)): ?><a href="?tab=logs&pagina=<?= $i ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_fecha=<?= urlencode($filtro_fecha) ?>" class="<?= $i==$pagina_actual ? 'active' : '' ?>"><?= $i ?></a><?php endif; endfor; ?>
            <?php if($pagina_actual < $total_paginas): ?><a href="?tab=logs&pagina=<?= $pagina_actual+1 ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_fecha=<?= urlencode($filtro_fecha) ?>"><i class="fas fa-angle-right"></i></a><a href="?tab=logs&pagina=<?= $total_paginas ?>&busqueda=<?= urlencode($busqueda) ?>&filtro_fecha=<?= urlencode($filtro_fecha) ?>"><i class="fas fa-angle-double-right"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Contenido: Alertas (CORREGIDO: eliminación por usuario específico) -->
    <div id="alertsTab" class="tab-content <?= $tab === 'alerts' ? 'active' : '' ?>">
        <!-- Formulario para crear alerta -->
        <div class="admin-panel">
            <h3><i class="fas fa-plus-circle"></i> Crear nueva alerta</h3>
            <form method="POST" class="form-alerta-modern">
                <input type="hidden" name="section" value="alerts">
                <input type="hidden" name="accion" value="crear_alerta">
                <div class="form-row">
                    <div class="form-field">
                        <label><i class="fas fa-tag"></i> Título *</label>
                        <input type="text" name="titulo" placeholder="Ej: Verificación de identidad" required>
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-user-edit"></i> Autor</label>
                        <input type="text" name="autor" value="Administrador" placeholder="Administrador">
                    </div>
                </div>
                <div class="form-field full-width">
                    <label><i class="fas fa-user-circle"></i> Usuario destinatario *</label>
                    <select name="usuario_id" required>
                        <option value="">-- Selecciona un usuario --</option>
                        <?php foreach ($usuarios_lista as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['nombre_completo']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field full-width">
                    <label><i class="fas fa-align-left"></i> Descripción *</label>
                    <textarea name="descripcion" rows="3" placeholder="Escribe aquí el contenido de la alerta..." required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Guardar alerta</button>
                    <button type="reset" class="btn-reset-form"><i class="fas fa-eraser"></i> Limpiar</button>
                </div>
            </form>
        </div>

        <!-- Filtro por usuario -->
        <div class="selector-perfil">
            <label><i class="fas fa-filter"></i> Filtrar por usuario:</label>
            <select id="filtroUsuario" onchange="window.location.href='?tab=alerts&ver_perfil='+this.value">
                <option value="">-- Todos los usuarios --</option>
                <?php foreach ($usuarios_lista as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($perfil_id == $u['id'] && !$modo_gestion) ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre_completo']) ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="window.location.href='?tab=alerts'" class="btn-filter">Ver todos</button>
        </div>

        <!-- Listado de alertas -->
        <div class="alertas-grid">
            <?php if ($modo_gestion && count($todas_alertas) > 0): ?>
                <?php foreach ($todas_alertas as $alerta): ?>
                    <div class="alerta-card">
                        <div class="alerta-header">
                            <div class="alerta-titulo">
                                <span><i class="fas fa-bell"></i> <?= htmlspecialchars($alerta['Titulo']) ?></span>
                                <span class="alerta-id">ID: <?= $alerta['ID'] ?></span>
                            </div>
                            <div class="alerta-autor">
                                <span><i class="fas fa-user"></i> Autor: <?= htmlspecialchars($alerta['Autor'] ?: 'Admin') ?></span>
                                <span><i class="fas fa-user-circle"></i> Usuario: <?= htmlspecialchars($alerta['usuario_nombre'] ?: 'ID: ' . $alerta['Usuario_id']) ?></span>
                            </div>
                        </div>
                        <div class="alerta-body">
                            <?= nl2br(htmlspecialchars($alerta['Descripcion'])) ?>
                        </div>
                        <div class="alerta-footer">
                            <form method="POST" onsubmit="return confirm('¿Eliminar esta alerta permanentemente?')">
                                <input type="hidden" name="section" value="alerts">
                                <input type="hidden" name="accion" value="eliminar_alerta">
                                <input type="hidden" name="alerta_id" value="<?= $alerta['ID'] ?>">
                                <button type="submit" class="btn-icon btn-delete" style="background:none;border:none;color:rgb(226,43,150);cursor:pointer;">
                                    <i class="fas fa-trash-alt"></i> Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php elseif (!$modo_gestion && $perfil_id > 0): ?>
                <?php if (count($alertas) > 0): ?>
                    <?php foreach ($alertas as $alerta): ?>
                        <div class="alerta-card">
                            <div class="alerta-header">
                                <div class="alerta-titulo">
                                    <span><i class="fas fa-bell"></i> <?= htmlspecialchars($alerta['Titulo']) ?></span>
                                </div>
                                <div class="alerta-autor">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($alerta['Autor'] ?: 'Administración') ?>
                                </div>
                            </div>
                            <div class="alerta-body">
                                <?= nl2br(htmlspecialchars($alerta['Descripcion'])) ?>
                            </div>
                            <div class="alerta-footer">
                                <form method="POST" onsubmit="return confirm('¿Eliminar esta alerta?')">
                                    <input type="hidden" name="section" value="alerts">
                                    <input type="hidden" name="accion" value="eliminar_alerta">
                                    <input type="hidden" name="alerta_id" value="<?= $alerta['ID'] ?>">
                                    <button type="submit" class="btn-icon btn-delete" style="background:none;border:none;color:rgb(226,43,150);cursor:pointer;">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column:1/-1; text-align:center; padding:3rem;">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No hay alertas para este usuario</h3>
                        <p>Puedes crear una usando el formulario superior.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column:1/-1; text-align:center; padding:3rem;">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No hay alertas registradas</h3>
                    <p>Crea la primera alerta usando el formulario.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.getElementById(tab + 'Tab').classList.add('active');
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        event.currentTarget.classList.add('active');
        window.history.pushState({}, '', '?tab=' + tab);
    }
    // Filtros logs
    const btnBuscar = document.getElementById('btnBuscar');
    const btnReset = document.getElementById('btnReset');
    const searchInput = document.getElementById('searchInput');
    const fechaFilter = document.getElementById('fechaFilter');
    if(btnBuscar) {
        btnBuscar.onclick = () => { let params = new URLSearchParams(); if(searchInput.value.trim()) params.set('busqueda', searchInput.value.trim()); if(fechaFilter.value) params.set('filtro_fecha', fechaFilter.value); params.set('tab','logs'); params.set('pagina','1'); window.location.href = '?' + params.toString(); };
        btnReset.onclick = () => { window.location.href = '?tab=logs'; };
        if(searchInput) searchInput.addEventListener('keypress', e => { if(e.key === 'Enter') btnBuscar.click(); });
        if(fechaFilter) fechaFilter.addEventListener('keypress', e => { if(e.key === 'Enter') btnBuscar.click(); });
    }
    window.filtrarUsuario = function(usuario) { let params = new URLSearchParams(); params.set('busqueda', usuario); params.set('tab','logs'); params.set('pagina','1'); window.location.href = '?' + params.toString(); };
    // Modal para ver identificación
    const modal = document.createElement('div'); modal.id = 'modalIdent'; modal.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);justify-content:center;align-items:center;z-index:1000;'; modal.innerHTML = '<div style="background:white;border-radius:1rem;padding:1rem;max-width:90%;max-height:90%;"><span id="closeModal" style="float:right;cursor:pointer;font-size:1.5rem;">&times;</span><h3><i class="fas fa-id-card"></i> Documento de identidad</h3><img id="modalImg" style="max-width:100%;max-height:70vh;margin-top:1rem;"><p style="margin-top:1rem;font-size:0.8rem;">Verifica que coincida con el perfil.</p></div>';
    document.body.appendChild(modal);
    document.querySelectorAll('.ver-id').forEach(btn => { btn.addEventListener('click', () => { document.getElementById('modalImg').src = btn.dataset.img; modal.style.display = 'flex'; }); });
    document.getElementById('closeModal')?.addEventListener('click', () => modal.style.display = 'none');
    window.onclick = (e) => { if(e.target === modal) modal.style.display = 'none'; };
</script>
</body>
</html>
<?php $conn->close(); ?>
