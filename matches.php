<?php
// matches.php - Mostrar todos los matches del usuario
session_start();
require_once 'Required/conect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$usuario_id = $_SESSION['user_id'];
$mensaje = '';

// Verificar si se acaba de obtener un nuevo match
if (isset($_GET['match']) && $_GET['match'] == 'ok') {
    $mensaje = "¡Es un match mutuo! Ahora puedes ver los datos de contacto.";
}

// Obtener todos los matches del usuario actual
$sql = "SELECT 
            CASE 
                WHEN m.usuario1_id = ? THEN m.usuario2_id 
                ELSE m.usuario1_id 
            END AS match_id,
            u.nombre_completo,
            u.foto_perfil,
            u.ciudad,
            u.busca,
            TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE()) AS edad,
            u.email,
            u.telefono,
            u.bio,
            m.created_at AS fecha_match
        FROM matches m
        JOIN usuarios u ON (u.id = m.usuario1_id OR u.id = m.usuario2_id)
        WHERE (m.usuario1_id = ? OR m.usuario2_id = ?) AND u.id != ?
        ORDER BY m.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $usuario_id, $usuario_id, $usuario_id, $usuario_id);
$stmt->execute();
$matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener datos del usuario actual para el navbar
$stmt_user = $conn->prepare("SELECT nombre_completo, foto_perfil FROM usuarios WHERE id = ?");
$stmt_user->bind_param("i", $usuario_id);
$stmt_user->execute();
$current_user = $stmt_user->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PotroTinder | Matches</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #fefaf7 0%, #f0f5ec 100%); min-height: 100vh; }

        /* Navbar */
        .navbar { background: white; box-shadow: 0 2px 15px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; backdrop-filter: blur(8px); background: rgba(255, 255, 250, 0.98); padding: 0.8rem 2rem; }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
        .logo a { font-size: 1.6rem; font-weight: 800; text-decoration: none; color: rgb(226, 43, 150); display: flex; align-items: center; gap: 8px; }
        .logo i { color: rgb(33, 100, 16); }
        .nav-links { display: flex; align-items: center; gap: 2rem; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; color: #4a5a40; font-weight: 500; transition: color 0.2s; display: flex; align-items: center; gap: 8px; }
        .nav-links a:hover { color: rgb(226, 43, 150); }
        .nav-links a.active { color: rgb(226, 43, 150); border-bottom: 2px solid rgb(226, 43, 150); }
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-btn { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .profile-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid rgb(226, 43, 150); }
        .dropdown-content { display: none; position: absolute; right: 0; background: white; min-width: 180px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-radius: 1rem; overflow: hidden; z-index: 200; }
        .dropdown-content a { display: block; padding: 0.8rem 1.2rem; text-decoration: none; color: #3a4634; transition: background 0.2s; }
        .dropdown-content a:hover { background: #f5f9f0; color: rgb(226, 43, 150); }
        .profile-dropdown:hover .dropdown-content { display: block; }

        /* Contenido principal */
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { text-align: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 2rem; color: rgb(33, 100, 16); margin-bottom: 0.5rem; }
        .page-header p { color: #6c7a64; }

        /* Grid de matches */
        .matches-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        .match-card { background: white; border-radius: 1.5rem; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.08); transition: transform 0.2s; }
        .match-card:hover { transform: translateY(-5px); }
        .match-header { display: flex; gap: 1rem; padding: 1rem; background: #f8faf5; }
        .match-img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid rgb(226, 43, 150); }
        .match-info { flex: 1; }
        .match-name { font-size: 1.2rem; font-weight: 700; color: #2c3a26; display: flex; justify-content: space-between; }
        .match-age { background: rgba(226, 43, 150, 0.1); padding: 0.2rem 0.6rem; border-radius: 40px; font-size: 0.8rem; color: rgb(226, 43, 150); }
        .match-location { font-size: 0.85rem; color: #7e8c74; margin-top: 0.3rem; display: flex; align-items: center; gap: 4px; }
        .match-badge { display: inline-block; background: rgba(33, 100, 16, 0.1); padding: 0.2rem 0.8rem; border-radius: 40px; font-size: 0.7rem; font-weight: 600; color: rgb(33, 100, 16); margin-top: 0.3rem; }
        .match-body { padding: 1rem; border-top: 1px solid #e2e8dc; }
        .match-contact { background: #e8f0e4; padding: 0.8rem; border-radius: 1rem; margin-bottom: 1rem; }
        .match-contact p { margin: 0.3rem 0; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; }
        .match-contact i { width: 25px; color: rgb(33, 100, 16); }
        .match-bio { font-size: 0.85rem; color: #5a6e50; margin-bottom: 1rem; line-height: 1.4; }
        .match-actions { display: flex; gap: 0.8rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 2rem; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-profile { background: rgb(33, 100, 16); color: white; }
        .btn-profile:hover { background: #1f6e12; }
        .btn-unmatch { background: #e2e8dc; color: #721c24; }
        .btn-unmatch:hover { background: #f8d7da; }
        .empty-message { text-align: center; padding: 4rem; background: white; border-radius: 2rem; color: #8c9c82; }
        .alert { background: #d4edda; color: #155724; padding: 1rem; border-radius: 1rem; margin-bottom: 1rem; text-align: center; }
        footer { text-align: center; padding: 2rem; color: #9aab90; font-size: 0.75rem; }
        @media (max-width: 768px) { .nav-container { flex-direction: column; } .matches-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <div class="logo"><a href="index.php"><i class="fas fa-heart"></i> PotroTinder</a></div>
        <div class="nav-links">
            <a href="principal.php"><i class="fas fa-home"></i> Inicio</a>
            <a href="matches.php" class="active"><i class="fas fa-star"></i> Mis matches</a>
            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                <a href="Admin/Dashboard.php" style="color: rgb(226,43,150);"><i class="fas fa-user-shield"></i> Admin</a>
            <?php endif; ?>
            <div class="profile-dropdown">
                <div class="profile-btn">
                    <img src="Uploads/profile/<?= htmlspecialchars($current_user['foto_perfil']) ?>" class="profile-img" onerror="this.src='https://randomuser.me/api/portraits/lego/1.jpg'">
                    <span><?= htmlspecialchars(explode(' ', $current_user['nombre_completo'])[0]) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="dropdown-content">
                    <a href="perfil.php?id=<?= $_SESSION['user_id'] ?>"><i class="fas fa-user"></i> Mi perfil</a>
                    <a href="config.php"><i class="fas fa-sliders-h"></i> Ajustes</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<main class="container">
    <div class="page-header">
        <h1><i class="fas fa-heart"></i> Mis matches</h1>
        <p>Personas con las que tienes conexión mutua</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if (empty($matches)): ?>
        <div class="empty-message">
            <i class="fas fa-heart-broken" style="font-size: 3rem; color: #cbdcc1; margin-bottom: 1rem; display: block;"></i>
            <p>No tienes matches aún.</p>
            <p style="font-size: 0.85rem;">Explora la galería y da likes a personas que te interesen. Si ellas también te dan like, ¡aparecerán aquí!</p>
            <a href="principal.php" style="display: inline-block; margin-top: 1rem; color: rgb(226,43,150);">Volver a la galería</a>
        </div>
    <?php else: ?>
        <div class="matches-grid">
            <?php foreach ($matches as $match): ?>
                <div class="match-card">
                    <div class="match-header">
                        <img src="Uploads/profile/<?= htmlspecialchars($match['foto_perfil']) ?>" alt="Foto" class="match-img" onerror="this.src='https://randomuser.me/api/portraits/lego/1.jpg'">
                        <div class="match-info">
                            <div class="match-name">
                                <?= htmlspecialchars($match['nombre_completo']) ?>
                                <span class="match-age"><?= $match['edad'] ?> años</span>
                            </div>
                            <div class="match-location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($match['ciudad'] ?: 'No especificada') ?></div>
                            <div class="match-badge"><i class="fas fa-heart"></i> Busca: <?= htmlspecialchars($match['busca']) ?></div>
                            <div class="match-badge" style="background: rgba(226,43,150,0.1); color: rgb(226,43,150);"><i class="fas fa-calendar-alt"></i> Match desde: <?= date('d/m/Y', strtotime($match['fecha_match'])) ?></div>
                        </div>
                    </div>
                    <div class="match-body">
                        <div class="match-contact">
                            <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?= htmlspecialchars($match['email']) ?></p>
                            <p><i class="fas fa-phone"></i> <strong>Teléfono:</strong> <?= htmlspecialchars($match['telefono'] ?: 'No registrado') ?></p>
                        </div>
                        <div class="match-bio">
                            <strong><i class="fas fa-comment"></i> Sobre mí:</strong><br>
                            <?= nl2br(htmlspecialchars(substr($match['bio'] ?: 'Este usuario aún no ha escrito su biografía.', 0, 150))) ?>
                            <?= strlen($match['bio'] ?? '') > 150 ? '...' : '' ?>
                        </div>
                        <div class="match-actions">
                            <a href="perfil.php?id=<?= $match['match_id'] ?>" class="btn btn-profile"><i class="fas fa-user-circle"></i> Ver perfil</a>
                            <a href="Api/unmatch.php?id=<?= $match['match_id'] ?>" class="btn btn-unmatch" onclick="return confirm('¿Eliminar este match? Ya no podrás ver sus datos de contacto.')"><i class="fas fa-trash-alt"></i> Eliminar match</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer>
    <i class="fas fa-heart" style="color: rgb(226, 43, 150);"></i> POTROTINDER <br> ESTE PROYECTO NO ESTA ASOCIADO A LA UNIVERSIDAD AUTONOMA DEL ESTADO DE MEXICO
</footer>

</body>
</html>