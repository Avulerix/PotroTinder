<?php
// principal.php - Galería de solteros con validación de bloqueo
session_start();
require_once 'Required/conect.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$usuario_actual = $_SESSION['user_id'];
$anterior = $_SERVER['HTTP_REFERER'] ?? 'principal.php';

// Verificar si el usuario está bloqueado (por identificación falsa/inconsistente)
$stmt_bloqueo = $conn->prepare("SELECT estado, identidad_verificada, motivo_bloqueo, foto_identificacion FROM usuarios WHERE id = ?");
$stmt_bloqueo->bind_param("i", $usuario_actual);
$stmt_bloqueo->execute();
$user_status = $stmt_bloqueo->get_result()->fetch_assoc();

// Si el usuario está bloqueado, mostrar mensaje y no permitir interacción
if ($user_status['estado'] === 'bloqueado') {
    $motivo = $user_status['motivo_bloqueo'];
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Cuenta bloqueada - PotroTinder</title>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
        <style>
            body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #fefaf7 0%, #f0f5ec 100%); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .blocked-card { background: white; border-radius: 2rem; padding: 2.5rem; max-width: 500px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
            .blocked-card i { font-size: 4rem; color: rgb(226,43,150); margin-bottom: 1rem; }
            .blocked-card h1 { color: rgb(33,100,16); margin-bottom: 1rem; }
            .btn { display: inline-block; background: rgb(33,100,16); color: white; padding: 0.8rem 1.5rem; border-radius: 2rem; text-decoration: none; margin-top: 1rem; }
        </style>
    </head>
    <body>
        <div class='blocked-card'>
            <i class='fas fa-ban'></i>
            <h1>Cuenta bloqueada</h1>
            <p>Tu cuenta ha sido bloqueada por ciertos motivos:  </p>
            <p>";
            	echo "
                	<h1 style='color: red; width: 99%; word-break:break-all;'>
                    	$motivo
					</h1>
                    ";
            echo "
            </p>
            <p>Para desbloquear tu cuenta, debes atender a las indicaciones de administracion.</p>
            <a href='config.php' class='btn'>Ir A Configuracion</a>
            <br><br>
            <a href='logout.php' style='color: #7e8c74;'>Cerrar sesión</a>
        </div>
    </body>
    </html>";
    exit;
}
elseif ($user_status['estado'] === 'espera') {
    $motivo = $user_status['motivo_bloqueo'];
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Alerta - PotroTinder</title>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
        <style>
            body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #fefaf7 0%, #f0f5ec 100%); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .blocked-card { background: white; border-radius: 2rem; padding: 2.5rem; max-width: 500px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
            .blocked-card i { font-size: 4rem; color: rgb(226,43,150); margin-bottom: 1rem; }
            .blocked-card h1 { color: rgb(33,100,16); margin-bottom: 1rem; }
            .btn { display: inline-block; background: rgb(33,100,16); color: white; padding: 0.8rem 1.5rem; border-radius: 2rem; text-decoration: none; margin-top: 1rem; }
        </style>
    </head>
    <body>
        <div class='blocked-card'>
            <i class='fas fa-warning'></i>
            <h1>Sistema En Pausa</h1>
            <p>El sitio ha sido pausado por ciertos motivos:  </p>
            <p>
                <h1 style='color: red; width: 99%; word-break:break-all;'>
                	Mantenimiento Del Sitio
				</h1>
            </p>
            <p>La pagina se habilitara a la brevedad.</p>
            <a href='logout.php' style='color: #7e8c74;'>Cerrar sesión</a>
        </div>
    </body>
    </html>";
    exit;
}

// Obtener preferencia de género del usuario actual
$stmt_pref = $conn->prepare("SELECT buscadogenero FROM usuarios WHERE id = ?");
$stmt_pref->bind_param("i", $usuario_actual);
$stmt_pref->execute();
$pref_result = $stmt_pref->get_result();
$pref = $pref_result->fetch_assoc();
$busca_sexo = $pref['buscadogenero'] ?? 'D';

// Construir consulta de solteros (excluyendo al actual y excluyendo bloqueados)
$sql = "SELECT 
        id, 
        nombre_completo, 
        email, 
        telefono, 
        bio, 
        ciudad, 
        plantel, 
        TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad, 
        busca, 
        genero, 
        foto_perfil 
        FROM usuarios 
        WHERE id != ? AND estado = 'activo'";

if($busca_sexo == 'H') {
    $sql .= " AND genero = 'hombre'";
}
elseif($busca_sexo == 'M') {
    $sql .= " AND genero = 'mujer'";
}
elseif($busca_sexo == 'TM') {
    $sql .= " AND genero = 'therianmujer'";
}
elseif($busca_sexo == 'TH') {
    $sql .= " AND genero = 'therianhombre'";
}
$sql .= " ORDER BY RAND() LIMIT 30";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_actual);
$stmt->execute();
$result = $stmt->get_result();
$solteros = $result->fetch_all(MYSQLI_ASSOC);

// Datos del usuario actual para el navbar
$stmt_user = $conn->prepare("SELECT nombre_completo, foto_perfil, identidad_verificada FROM usuarios WHERE id = ?");
$stmt_user->bind_param("i", $usuario_actual);
$stmt_user->execute();
$current_user = $stmt_user->get_result()->fetch_assoc();




?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PotroTinder | Galería</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ========== ESTILOS GENERALES ========== */
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
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-btn { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .profile-img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid rgb(226, 43, 150); }
        .dropdown-content { display: none; position: absolute; right: 0;  background: white; min-width: 180px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-radius: 1rem; zoom: 90%; text-align: center;  overflow: hidden; z-index: 200;}
        .dropdown-content a { display: block; padding: 0.8rem 1.2rem; text-decoration: none; color: #3a4634; transition: background 0.5s; }
        .dropdown-content a:hover { background: #f5f9f0; color: rgb(226, 43, 150); }
        .profile-dropdown:hover .dropdown-content { display: block; }

        /* Contenido principal */
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { text-align: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 2rem; color: rgb(33, 100, 16); margin-bottom: 0.5rem; }
        .page-header p { color: #6c7a64; }

        /* Grid de tarjetas */
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem; }
        .profile-card { background: white; border-radius: 1.5rem; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05); transition: transform 0.3s ease, box-shadow 0.3s ease; cursor: pointer; }
        .profile-card:hover { transform: translateY(-8px); box-shadow: 0 20px 35px rgba(0,0,0,0.1); }
        .card-image { width: 100%; height: 320px; object-fit: cover; background: #e9ece5; }
        .card-info { padding: 1.2rem; }
        .card-name { font-size: 1.2rem; font-weight: 700; color: #2c3a26; margin-bottom: 0.3rem; display: flex; justify-content: space-between; align-items: center; }
        .card-age { background: rgba(226, 43, 150, 0.1); padding: 0.2rem 0.6rem; border-radius: 40px; font-size: 0.8rem; color: rgb(226, 43, 150); }
        .card-location { font-size: 0.85rem; color: #7e8c74; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 4px; }
        .card-badge { display: inline-block; background: rgba(33, 100, 16, 0.1); padding: 0.2rem 0.8rem; border-radius: 40px; font-size: 0.7rem; font-weight: 600; color: rgb(33, 100, 16); margin-top: 0.5rem; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; max-width: 550px; width: 90%; border-radius: 2rem; overflow: hidden; animation: modalFadeIn 0.3s; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .modal-header { background: rgb(33, 100, 16); color: white; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 1.3rem; }
        .close-modal { background: none; border: none; color: white; font-size: 1.8rem; cursor: pointer; }
        .modal-body { padding: 1.5rem; display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .modal-img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid rgb(226, 43, 150); }
        .modal-info { flex: 1; }
        .modal-info p { margin: 0.5rem 0; display: flex; align-items: center; gap: 10px; }
        .modal-info i { width: 25px; color: rgb(226, 43, 150); }
        .modal-bio { background: #f8faf5; padding: 1rem; border-radius: 1rem; margin-top: 1rem; }
        .modal-actions { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .btn-like, .btn-profile { padding: 0.6rem 1.2rem; border-radius: 2rem; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-like { background: rgb(226, 43, 150); color: white; border: none; cursor: pointer; }
        .btn-profile { background: rgb(33, 100, 16); color: white; }
        .btn-like:hover, .btn-profile:hover { opacity: 0.9; transform: translateY(-2px); }
        footer { text-align: center; padding: 2rem; color: #9aab90; font-size: 0.75rem; }
        @media (max-width: 768px) { .nav-container { flex-direction: column; } .gallery-grid { gap: 1rem; } .modal-body { flex-direction: column; align-items: center; text-align: center; } .modal-info p { justify-content: center; } }
    @keyframes pulse {
        0%, 100% { transform: scale(1); background-position: 0% 0%; }
        50% { transform: scale(1.03); background-position: 100% 0%; }
    }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <div class="logo">
        <a href="index.html" style="color: rgb(33, 100, 16);">
            <i class="fas fa-heart" style=" color: rgb(226, 43, 150);; font-size: 0.9em;"></i> 
            Potro-Tinder
        </a>
    </div>
        <div class="nav-links">
            <a href="principal.php"><i class="fas fa-home"></i> Inicio</a>
            <a href="matches.php" style="color: rgb(226,43,150);"><i class="fas fa-star"></i> Mis matches</a>
            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
            	<a href="Admin/adm.php" style="color: blue;"><i class="fas fa-user-shield"></i> Panel Administrativo</a>
            <?php endif; ?>
            <a href="warning.php" style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #ff0000, #ff4444, #cc0000); background-size: 200% auto; color: white; padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: 800; font-size: 0.9rem; letter-spacing: 0.5px; box-shadow: 0 0 15px rgba(255,0,0,0.5); animation: pulse 1s ease-in-out infinite; border: 1px solid rgba(255,255,255,0.3); transition: all 0.3s ease;" 
   onmouseover="this.style.transform='scale(1.05)'; this.style.background='linear-gradient(135deg, #ff3333, #ff5555, #dd0000)'; this.style.boxShadow='0 0 25px rgba(255,0,0,0.8)';" 
   onmouseout="this.style.transform='scale(1)'; this.style.background='linear-gradient(135deg, #ff0000, #ff4444, #cc0000)'; this.style.boxShadow='0 0 15px rgba(255,0,0,0.5)';">
    <i class="fas fa-exclamation-triangle" style="font-size: 1.1rem;"></i>
    Tus Alertas
</a>
            <div class="profile-dropdown">
                <div class="profile-btn">
                    <img src="Uploads/profile/<?= htmlspecialchars($current_user['foto_perfil']) ?>" class="profile-img" onerror="this.src='https://randomuser.me/api/portraits/lego/1.jpg'">
                    <span><?= htmlspecialchars(explode(' ', $current_user['nombre_completo'])[0]) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="dropdown-content">
                    <a href="perfil.php?id="><i class="fas fa-user"> </i> Mi perfil</a>
                    <a href="config.php"><i class="fas fa-sliders-h"> </i> Ajustes</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"> </i> Cerrar <br> sesión</a>
                </div>
            </div>
        </div>
    </div>
</nav>
   
    
    <!-----APERTURA MENSAJE-------->
    <div class="hm" style="display: flex; justify-content: center;">
        <?php
            echo $help_mensaje;
        ?>
	</div>
	<!------CIERRE MENSAJE-------->
    
    
<main class="container">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Descubre solteros en tu universidad</h1>
        <p>Haz clic en cualquier foto para ver el contacto y más detalles</p>
    </div>

    <?php if (empty($solteros)): ?>
        <div class="empty-message" style="text-align:center; padding:3rem; background:white; border-radius:2rem;">
            <i class="fas fa-heart-broken" style="font-size:3rem; color:#cbdcc1;"></i>
            <p>No hay solteros para mostrar en este momento.</p>
            <p>Vuelve más tarde o amplía tus preferencias en <a href="config.php">Ajustes</a>.</p>
        </div>
    <?php else: ?>
        <div class="gallery-grid" id="galleryGrid">
            <?php foreach ($solteros as $soltero): ?>
                <div class="profile-card"
                     data-id="<?= $soltero['id'] ?>"
                     data-nombre="<?= htmlspecialchars($soltero['nombre_completo']) ?>"
                     data-edad="<?= $soltero['edad'] ?>"
                     data-ciudad="<?= htmlspecialchars($soltero['ciudad'] ?: 'No especificado') ?>"
                     data-plantel="<?= htmlspecialchars($soltero['plantel'] ?: 'No especificado') ?>"
                     data-email="<?= htmlspecialchars($soltero['email']) ?>"
                     data-telefono="<?= htmlspecialchars($soltero['telefono'] ?: 'No registrado') ?>"
                     data-bio="<?= htmlspecialchars($soltero['bio'] ?: 'Este usuario aún no ha escrito su biografía.') ?>"
                     data-foto="Uploads/profile/<?= $soltero['foto_perfil'] ?>"
                     data-busca="<?= htmlspecialchars($soltero['busca']) ?>">
                    <img src="Uploads/profile/<?= htmlspecialchars($soltero['foto_perfil']) ?>" alt="Foto" class="card-image" onerror="this.src='https://randomuser.me/api/portraits/lego/1.jpg'">
                    <div class="card-info">
                        <div class="card-name">
                            <?= htmlspecialchars($soltero['nombre_completo']) ?>
                            <span class="card-age"><?= $soltero['edad'] ?> años</span>
                        </div>
                        <div class="card-location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($soltero['ciudad'] ?: 'No especificada') ?></div>
                        <div class="card-badge"><i class="fas fa-heart"></i> Busca: <?= htmlspecialchars($soltero['busca']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer>
    <i class="fas fa-heart" style="color: rgb(226, 43, 150);"></i>PotroTinder <br>ESTE PROYECTO NO ESTA ASOCIADO A LA UNIVERSIDAD AUTONOMA DEL ESTADO DE MEXICO
</footer>

<!-- Modal -->
<div id="contactModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user"></i> Detalles de contacto</h2>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <img id="modalFoto" class="modal-img" src="" alt="Foto perfil">
            <div class="modal-info">
                <p><i class="fas fa-user"></i> <strong id="modalNombre"></strong>, <span id="modalEdad"></span> años</p>
                <p><i class="fas fa-map-marker-alt"></i> <span id="modalCiudad"></span></p>
                <p><i class="fas fa-school"></i> <span id="modalPlantel"></span></p>
                <p><i class="fas fa-heart"></i> Busca: <span id="modalBusca"></span></p>
                <div class="modal-bio">
                    <strong><i class="fas fa-comment"></i> Sobre mí:</strong>
                    <p id="modalBio"></p>
                </div>
                <div class="modal-actions">
                    <button id="modalLikeBtn" class="btn-like"><i class="fas fa-heart" style="color: white;"></i> Dar like</button>
                    <a id="modalPerfilLink" href="#" class="btn-profile"><i class="fas fa-user-circle" style="color: white;"></i> Ver perfil</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Obtener elementos del modal
    const modal = document.getElementById('contactModal');
    const closeModalBtn = document.querySelector('.close-modal');
    const modalFoto = document.getElementById('modalFoto');
    const modalNombre = document.getElementById('modalNombre');
    const modalEdad = document.getElementById('modalEdad');
    const modalTelefono = document.getElementById('modalTelefono');
    const modalBusca = document.getElementById('modalBusca');
    const modalPlantel = document.getElementById('modalPlantel');
    const modalBio = document.getElementById('modalBio');
    const modalLikeBtn = document.getElementById('modalLikeBtn');
    const modalPerfilLink = document.getElementById('modalPerfilLink');

    let currentUserId = null;

    // Función para abrir modal con datos de la tarjeta
    function openModal(card) {
        currentUserId = card.dataset.id;
        modalFoto.src = card.dataset.foto;
        modalNombre.textContent = card.dataset.nombre;
        modalEdad.textContent = card.dataset.edad;
        modalCiudad.textContent = card.dataset.ciudad;
        
        modalPlantel.textContent = card.dataset.plantel;
        
        modalBusca.textContent = card.dataset.busca;
        modalBio.textContent = card.dataset.bio;
        modalPerfilLink.href = 'perfil.php?id=' + currentUserId;
        modal.style.display = 'flex';
    }

    // Asignar evento click a todas las tarjetas
    const cards = document.querySelectorAll('.profile-card');
    cards.forEach(card => {
        card.addEventListener('click', (e) => {
            // Evitar que el click en la imagen o texto cierre algo
            e.stopPropagation();
            openModal(card);
        });
    });

    // Cerrar modal
    closeModalBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });
    window.addEventListener('click', (e) => {
        if (e.target === modal) modal.style.display = 'none';
    });

    // Dar like (redirige a like.php)
    modalLikeBtn.addEventListener('click', () => {
        if (currentUserId) {
            window.location.href = 'Api/like.php?id=' + currentUserId;
        }
    });
</script>
</body>
</html>
