<?php
// ==============================================
// SISTEMA DE ALERTAS POR USUARIO - POTROTINDER
// Muestra alertas asignadas a cada perfil
// ==============================================

require_once "Required/conect.php";
session_start();
if(empty($_SESSION['user_id'])){
    header('Location: login.html');
}

// Determinar si el usuario es administrador (esto deberías ajustarlo según tu lógica de autenticación)
// Por ahora, asumimos que hay una sesión con rol de usuario
$es_admin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
$usuario_actual_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Obtener el ID del perfil a mostrar (si viene por GET)
$perfil_id = isset($_GET['perfil_id']) ? (int)$_GET['perfil_id'] : $usuario_actual_id;

// Si es administrador y no se especificó perfil, mostrar panel de gestión
$modo_gestion = $es_admin && !isset($_GET['ver_perfil']);

// Procesar acciones de administrador (crear, editar, eliminar)
$mensaje = '';
$mensaje_tipo = '';

if ($es_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear_alerta') {
        $titulo = trim($_POST['titulo'] ?? '');
        $autor = trim($_POST['autor'] ?? '');
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if ($titulo && $usuario_id && $descripcion) {
            $stmt = $conn->prepare("INSERT INTO alertas (Titulo, Autor, Usuario_id, Descripcion) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $titulo, $autor, $usuario_id, $descripcion);
            if ($stmt->execute()) {
                $mensaje = "Alerta creada correctamente";
                $mensaje_tipo = "success";
            } else {
                $mensaje = "Error al crear alerta: " . $conn->error;
                $mensaje_tipo = "error";
            }
            $stmt->close();
        } else {
            $mensaje = "Todos los campos son obligatorios";
            $mensaje_tipo = "error";
        }
    }
    
    elseif ($accion === 'editar_alerta') {
        $alerta_id = (int)($_POST['alerta_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $autor = trim($_POST['autor'] ?? '');
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if ($alerta_id && $titulo && $descripcion) {
            $stmt = $conn->prepare("UPDATE alertas SET Titulo = ?, Autor = ?, Usuario_id = ?, Descripcion = ? WHERE ID = ?");
            $stmt->bind_param("ssisi", $titulo, $autor, $usuario_id, $descripcion, $alerta_id);
            if ($stmt->execute()) {
                $mensaje = "Alerta actualizada correctamente";
                $mensaje_tipo = "success";
            } else {
                $mensaje = "Error al actualizar: " . $conn->error;
                $mensaje_tipo = "error";
            }
            $stmt->close();
        }
    }
    
    elseif ($accion === 'eliminar_alerta') {
        $alerta_id = (int)($_POST['alerta_id'] ?? 0);
        if ($alerta_id) {
            $stmt = $conn->prepare("DELETE FROM alertas WHERE ID = ?");
            $stmt->bind_param("i", $alerta_id);
            if ($stmt->execute()) {
                $mensaje = "Alerta eliminada correctamente";
                $mensaje_tipo = "success";
            } else {
                $mensaje = "Error al eliminar: " . $conn->error;
                $mensaje_tipo = "error";
            }
            $stmt->close();
        }
    }
}

// Obtener información del usuario (para mostrar el nombre)
$usuario_nombre = '';
if ($perfil_id > 0) {
    $stmt = $conn->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $perfil_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $usuario_nombre = $row['nombre_completo'];
    }
    $stmt->close();
}

// Obtener lista de usuarios (para administradores)
$usuarios_lista = [];
if ($es_admin) {
    $result = $conn->query("SELECT id, nombre_completo, email FROM usuarios ORDER BY nombre_completo");
    while ($row = $result->fetch_assoc()) {
        $usuarios_lista[] = $row;
    }
}

// Obtener alertas del perfil seleccionado
$alertas = [];
if ($perfil_id > 0) {
    $stmt = $conn->prepare("SELECT ID, Titulo, Autor, Usuario_id, Descripcion FROM alertas WHERE Usuario_id = ? ORDER BY ID DESC");
    $stmt->bind_param("i", $perfil_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $alertas[] = $row;
    }
    $stmt->close();
}

// Si es administrador y está en modo gestión, obtener todas las alertas
$todas_alertas = [];
if ($es_admin && $modo_gestion) {
    $result = $conn->query("
        SELECT a.ID, a.Titulo, a.Autor, a.Usuario_id, a.Descripcion, u.nombre_completo as usuario_nombre 
        FROM alertas a 
        LEFT JOIN usuarios u ON a.Usuario_id = u.id 
        ORDER BY a.ID DESC
    ");
    while ($row = $result->fetch_assoc()) {
        $todas_alertas[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas | PotroTinder</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fefaf7 0%, #f0f5ec 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .logo h1 {
            font-size: 1.5rem;
            color: rgb(33,100,16);
        }
        .logo p {
            color: #6c7a64;
            font-size: 0.85rem;
        }
        .nav-links {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .nav-links a {
            text-decoration: none;
            color: #4a5c42;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            transition: all 0.2s;
        }
        .nav-links a:hover {
            background: rgba(33,100,16,0.1);
            color: rgb(33,100,16);
        }
        .btn-admin {
            background: rgb(226,43,150);
            color: white !important;
        }
        .btn-admin:hover {
            background: #c41e7a !important;
        }
        
        /* Mensajes */
        .mensaje {
            padding: 1rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .mensaje.success {
            background: #d4edda;
            color: rgb(33,100,16);
            border-left: 4px solid rgb(33,100,16);
        }
        .mensaje.error {
            background: #ffe6e6;
            color: rgb(226,43,150);
            border-left: 4px solid rgb(226,43,150);
        }
        
        /* Perfil Header */
        .perfil-header {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .perfil-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgb(33,100,16), rgb(226,43,150));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .perfil-icon i {
            font-size: 2rem;
            color: white;
        }
        .perfil-info h2 {
            color: #2c3a26;
            margin-bottom: 0.25rem;
        }
        .perfil-info p {
            color: #6c7a64;
        }
        .badge-count {
            background: rgb(226,43,150);
            color: white;
            padding: 0.25rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        /* Grid de Alertas */
        .alertas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .alerta-card {
            background: white;
            border-radius: 1.2rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid rgb(226,43,150);
        }
        .alerta-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }
        .alerta-header {
            padding: 1rem 1.2rem;
            background: linear-gradient(135deg, #fefaf7, #f5f9f2);
            border-bottom: 1px solid #e2e8dc;
        }
        .alerta-titulo {
            font-size: 1.1rem;
            font-weight: 700;
            color: rgb(33,100,16);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .alerta-titulo i {
            color: rgb(226,43,150);
        }
        .alerta-id {
            font-size: 0.7rem;
            color: #9aa89b;
            background: #edf3e8;
            padding: 0.2rem 0.5rem;
            border-radius: 1rem;
        }
        .alerta-autor {
            font-size: 0.75rem;
            color: #8da082;
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .alerta-body {
            padding: 1rem 1.2rem;
        }
        .alerta-descripcion {
            color: #4a5c42;
            line-height: 1.5;
            font-size: 0.9rem;
        }
        .alerta-footer {
            padding: 0.8rem 1.2rem;
            background: #fafef7;
            border-top: 1px solid #e2e8dc;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            padding: 0.4rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .btn-edit {
            color: rgb(33,100,16);
        }
        .btn-edit:hover {
            background: rgba(33,100,16,0.1);
        }
        .btn-delete {
            color: rgb(226,43,150);
        }
        .btn-delete:hover {
            background: rgba(226,43,150,0.1);
        }
        
        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 1.2rem;
            padding: 3rem;
            text-align: center;
            grid-column: 1 / -1;
        }
        .empty-state i {
            font-size: 3rem;
            color: rgb(226,43,150);
            margin-bottom: 1rem;
        }
        .empty-state h3 {
            color: #2c3a26;
            margin-bottom: 0.5rem;
        }
        
        /* Formulario Admin */
        .admin-panel {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .admin-panel h3 {
            color: rgb(33,100,16);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-alerta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c7a64;
            margin-bottom: 0.3rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            padding: 0.6rem 1rem;
            border: 1.5px solid #e2e8dc;
            border-radius: 0.8rem;
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: rgb(226,43,150);
        }
        .btn-submit {
            background: rgb(33,100,16);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
            cursor: pointer;
            align-self: flex-end;
        }
        .btn-submit:hover {
            background: #1f6e12;
        }
        
        /* Selector de perfil */
        .selector-perfil {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .selector-perfil label {
            font-weight: 600;
            color: #2c3a26;
        }
        .selector-perfil select {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            border: 1.5px solid #e2e8dc;
            flex: 1;
            min-width: 200px;
        }
        .selector-perfil button {
            background: rgb(33,100,16);
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .header { flex-direction: column; text-align: center; }
            .alertas-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <div class="logo">
            <h1><i class="fas fa-bell"></i> Sistema de Alertas</h1>
            <p>Gestión de notificaciones por usuario</p>
        </div>
        <div class="nav-links">
            <a href="index.html"><i class="fas fa-home"></i> Inicio</a>
            <a href="perfil.php"><i class="fas fa-user"></i> Mi Perfil</a>
            <a href="principal.php"><i class="fas fa-users"></i> Galeria</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Session</a>
            <?php if ($es_admin): ?>
                <a href="?modo_admin=1" class="btn-admin"><i class="fas fa-shield-alt"></i> Panel Admin</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Mensajes -->
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $mensaje_tipo; ?>">
            <i class="fas fa-<?php echo $mensaje_tipo === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($es_admin && $modo_gestion): ?>
        <!-- MODO ADMINISTRADOR - GESTIÓN COMPLETA -->
        <div class="admin-panel">
            <h3><i class="fas fa-plus-circle"></i> Crear nueva alerta</h3>
            <form method="POST" class="form-alerta">
                <input type="hidden" name="accion" value="crear_alerta">
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Título *</label>
                    <input type="text" name="titulo" placeholder="Ej: Verificación de cuenta">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Autor</label>
                    <input type="text" name="autor" placeholder="Administración" value="Administrador">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Usuario destinatario *</label>
                    <select name="usuario_id" required>
                        <option value="">Selecciona un usuario</option>
                        <?php foreach ($usuarios_lista as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['nombre_completo']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label><i class="fas fa-align-left"></i> Descripción *</label>
                    <textarea name="descripcion" rows="2" placeholder="Detalles de la alerta..." required></textarea>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Guardar alerta</button>
            </form>
        </div>
        
        <!-- Selector de filtro por usuario -->
        <div class="selector-perfil">
            <label><i class="fas fa-filter"></i> Filtrar por usuario:</label>
            <select id="filtroUsuario" onchange="window.location.href='?ver_perfil='+this.value">
                <option value="">-- Todos los usuarios --</option>
                <?php foreach ($usuarios_lista as $user): ?>
                    <option value="<?php echo $user['id']; ?>">
                        <?php echo htmlspecialchars($user['nombre_completo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button onclick="window.location.href='?modo_admin=1'"><i class="fas fa-eye"></i> Ver todos</button>
        </div>
        
        <!-- Lista de todas las alertas -->
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-list"></i> Todas las alertas del sistema</h3>
        <div class="alertas-grid">
            <?php if (count($todas_alertas) > 0): ?>
                <?php foreach ($todas_alertas as $alerta): ?>
                    <div class="alerta-card">
                        <div class="alerta-header">
                            <div class="alerta-titulo">
                                <t><i class="fas fa-bell"></i> <?php echo htmlspecialchars($alerta['Titulo']); ?></t>
                                <span class="alerta-id">ID: <?php echo $alerta['ID']; ?></span>
                            </div>
                            <div class="alerta-autor">
                                <i class="fas fa-user"></i> Autor: <?php echo htmlspecialchars($alerta['Autor'] ?: 'Admin'); ?>
                                <span style="margin-left: auto;">
                                    <i class="fas fa-user-circle"></i> Usuario: <?php echo htmlspecialchars($alerta['usuario_nombre'] ?: 'ID: ' . $alerta['Usuario_id']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="alerta-body">
                            <div class="alerta-descripcion">
                                <?php echo nl2br(htmlspecialchars($alerta['Descripcion'])); ?>
                            </div>
                        </div>
                        <div class="alerta-footer">
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Editar esta alerta?')">
                                <input type="hidden" name="accion" value="editar_alerta">
                                <input type="hidden" name="alerta_id" value="<?php echo $alerta['ID']; ?>">
                                <input type="hidden" name="titulo" value="<?php echo htmlspecialchars($alerta['Titulo']); ?>">
                                <input type="hidden" name="autor" value="<?php echo htmlspecialchars($alerta['Autor']); ?>">
                                <input type="hidden" name="usuario_id" value="<?php echo $alerta['Usuario_id']; ?>">
                                <input type="hidden" name="descripcion" value="<?php echo htmlspecialchars($alerta['Descripcion']); ?>">
                                <button type="button" class="btn-icon btn-edit" onclick="editarAlerta(this)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta alerta permanentemente?')">
                                <input type="hidden" name="accion" value="eliminar_alerta">
                                <input type="hidden" name="alerta_id" value="<?php echo $alerta['ID']; ?>">
                                <button type="submit" class="btn-icon btn-delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No hay alertas registradas</h3>
                    <p>Crea la primera alerta usando el formulario superior</p>
                </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($perfil_id > 0): ?>
        <!-- MODO USUARIO - Mostrar alertas del perfil -->
        <div class="perfil-header">
            <div class="perfil-icon">
                <i class="fas fa-user"></i>
            </div>
            <div class="perfil-info">
                <h2><?php echo htmlspecialchars($usuario_nombre ?: 'Perfil de usuario'); ?></h2>
                <p><i class="fas fa-bell"></i> Aqui se muestran tus notificaciones y alertas por parte de adminstracion</p>
            </div>
            <div style="margin-left: auto;">
                <span class="badge-count"> Tienes <?php echo count($alertas); ?> alertas nuevas</span>
            </div>
        </div>
        
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-bell"></i> Tus alertas</h3>
        
        <div class="alertas-grid">
            <?php if (count($alertas) > 0): ?>
                <?php foreach ($alertas as $alerta): ?>
                    <div class="alerta-card">
                        <div class="alerta-header">
                            <div class="alerta-titulo">
                                <span><i class="fas fa-bell"></i> <?php echo htmlspecialchars($alerta['Titulo']); ?></span>
                            </div>
                            <div class="alerta-autor">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($alerta['Autor'] ?: 'Administración'); ?>
                            </div>
                        </div>
                        <div class="alerta-body">
                            <div class="alerta-descripcion">
                                <?php echo nl2br(htmlspecialchars($alerta['Descripcion'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>En Horabuena.</p>
                    <h3>No tienes alertas</h3>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($es_admin && $perfil_id != $usuario_actual_id): ?>
            <div style="margin-top: 2rem; text-align: center;">
                <a href="?modo_admin=1" class="btn-submit" style="display: inline-block; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Volver al panel de administración
                </a>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <h3>Usuario no especificado</h3>
            <p>No se ha seleccionado un perfil válido para mostrar sus alertas.</p>
            <?php if (!$es_admin): ?>
                <p style="margin-top: 1rem;"><a href="perfil.php" class="btn-submit" style="display: inline-block; text-decoration: none;">Ir a mi perfil</a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function editarAlerta(btn) {
        const form = btn.closest('form');
        const alertaId = form.querySelector('[name="alerta_id"]').value;
        const tituloActual = form.querySelector('[name="titulo"]').value;
        const autorActual = form.querySelector('[name="autor"]').value;
        const usuarioId = form.querySelector('[name="usuario_id"]').value;
        const descripcionActual = form.querySelector('[name="descripcion"]').value;
        
        const nuevoTitulo = prompt("Editar título:", tituloActual);
        if (nuevoTitulo === null) return;
        const nuevaDescripcion = prompt("Editar descripción:", descripcionActual);
        if (nuevaDescripcion === null) return;
        const nuevoAutor = prompt("Editar autor:", autorActual) || autorActual;
        
        const newForm = document.createElement('form');
        newForm.method = 'POST';
        newForm.innerHTML = `
            <input type="hidden" name="accion" value="editar_alerta">
            <input type="hidden" name="alerta_id" value="${alertaId}">
            <input type="hidden" name="titulo" value="${escapeHtml(nuevoTitulo)}">
            <input type="hidden" name="autor" value="${escapeHtml(nuevoAutor)}">
            <input type="hidden" name="usuario_id" value="${usuarioId}">
            <input type="hidden" name="descripcion" value="${escapeHtml(nuevaDescripcion)}">
        `;
        document.body.appendChild(newForm);
        newForm.submit();
    }
    
    function escapeHtml(str) {
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
</script>
</body>
</html>
<?php $conn->close(); ?>