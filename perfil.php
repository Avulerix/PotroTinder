<?php
// perfil.php - Ver perfil propio
session_start();
require_once 'Required/conect.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

# $id = $_SESSION['user_id']; // Perfil propio
if(empty($_GET['id'])){
    $id = $_SESSION['user_id'];
}
else{
	$id = $_GET['id']; //Perfil ajeno o propio(En su defecto)   
}

$stmt = $conn->prepare("SELECT *, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo
    "
    	<html lang='es'>
	<head>
		<meta charset='UTF-8'>
		<meta name='viewport' content='width=device-width, initial-scale=1.0'>
		<title>Usuario Desconocido</title>
		<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
	</head>
	<body style='display: flex; align-items: center; justify-content: center; align-self: center; text-align: center; background: linear-gradient(135deg, #fefaf7 0%, #f0f5ec 100%);'>
		<div class='maincont'>
			<h2 class='scont' style='color: red;'>
				<span style='font-size: 220%;'>
					⚠
				</span>
                <br>
                Falla Al Mostrar El Perfil
                <br>
                <br>
				Regresa A La Pagina Anterior
				<br>
				Y Reintenta La visualizacion
                <br>
                Del Perfil
			</h2>
		</div>
	</body>
	</html>

    ";
    exit;
}
// Obtener fotos adicionales del usuario
$stmt_fotos = $conn->prepare("SELECT id, foto_url, es_principal FROM fotos_usuario WHERE usuario_id = ? ORDER BY orden ASC, id ASC");
$stmt_fotos->bind_param("i", $id);
$stmt_fotos->execute();
$fotos_result = $stmt_fotos->get_result();
$fotos = $fotos_result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PotroTinder | Perfil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #fefaf7 0%, #f0f5ec 100%); padding: 2rem; }
        .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 2rem; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .profile-header { display: flex; gap: 2rem; flex-wrap: wrap; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #e2e8dc; padding-bottom: 1.5rem; }
        .profile-img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid rgb(226,43,150); }
        .profile-info h1 { color: rgb(33,100,16); margin-bottom: 0.3rem; }
        .profile-info p { color: #6c7a64; margin: 0.3rem 0; }
        .info-section { margin: 1.5rem 0; }
        .info-section h3 { color: rgb(226,43,150); margin-bottom: 0.8rem; display: flex; align-items: center; gap: 8px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px,1fr)); gap: 1rem; background: #f8faf5; padding: 1.5rem; border-radius: 1.5rem; }
        .info-item { display: flex; align-items: center; gap: 12px; }
        .info-item i { width: 30px; color: rgb(33,100,16); font-size: 1.2rem; }
        .btn-edit { background: rgb(33,100,16); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 2rem; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-top: 1rem; }
        .btn-edit:hover { background: #1f6e12; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: rgb(33,100,16); text-decoration: none; }
        @media (max-width: 600px) { body { padding: 1rem; } .profile-header { flex-direction: column; text-align: center; } }

        /* Galería de fotos */
.fotos-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}
.foto-item {
    position: relative;
    border-radius: 1rem;
    overflow: hidden;
    cursor: pointer;
    /*aspect-ratio: 1 / 1;*/
    background: #f0f5ec;
}
.foto-item img {
    width: 100%;
    height: auto;
    object-fit: cover;
    transition: transform 0.2s;
}
.foto-item img:hover {
    transform: scale(1.05);
}
.foto-badge {
    position: absolute;
    bottom: 8px;
    left: 8px;
    background: rgba(33,100,16,0.85);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}
    </style>
</head>
<body>
<div class="container">
    <a href="principal.php" class="back-link">&larr; Volver a la galería</a>

    <div class="profile-header">
        <img src="Uploads/profile/<?= htmlspecialchars($user['foto_perfil']) ?>" alt="Foto de perfil" class="profile-img" onerror="this.src='https://randomuser.me/api/portraits/lego/1.jpg'">
        <div class="profile-info">
            <h1><?= htmlspecialchars($user['nombre_completo']) ?>, <?= $user['edad'] ?> años</h1>
            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($user['ciudad'] ?: 'No especificada') ?></p>
            
            <?php
            	if(($_SESSION['user_id']) == ($id)){
                    echo "
                			<a href='config.php' class='btn-edit'><i class='fas fa-edit'></i> Editar perfil</a>
                         ";
                }
        	?>
            
    	</div>
    </div>

    <div class="info-section">
        <h3><i class="fas fa-heart"></i> Preferencias del usuario</h3>
        <div class="info-grid">
            <div class="info-item"><i class="fas fa-venus-mars"></i> <span>Sus intenciones son: <strong><?= ucfirst(str_replace('_', ' ', $user['busca'])) ?></strong></span></div>
            <div class="info-item"><i class="fas fa-users"></i> <span>Interesado en conocer a: 
                <strong>
                    <?php 
                        if($user['buscadogenero'] == 'H'){
                            echo 'Hombres';
                        }
                        elseif($user['buscadogenero'] == 'M'){
                            echo 'Mujeres';
                        }
                		elseif($user['buscadogenero'] == 'TH'){
                            echo 'Therians Hombres';
                        }
                        elseif($user['buscadogenero'] == 'TM'){
                            echo ' Therians Mujeres';
                        }
                        else{
                            echo 'Ambos';
                        }
                    ?>
                </strong>
            </span></div>
            <div class="info-item"><i class="fas fa-calendar-alt"></i> <span>Miembro desde: <?= date('d/m/Y', strtotime($user['created_at'])) ?></span></div>
            <div class="info-item"><i class="fas fa-check-circle"></i> <span>Identidad: <?= $user['identidad_verificada'] ? 'Verificada' : 'Pendiente' ?></span></div>
        </div>
        <div class="info-section">
    <h3><i class="fas fa-images"></i> Galería de fotos</h3>
    <?php if (empty($fotos)): ?>
        <p style="color: #7e8c74;">No hay fotos adicionales.</p>
    <?php else: ?>
        <div class="fotos-gallery">
            <?php foreach ($fotos as $foto): ?>
                <div class="foto-item">
                    <img src="Uploads/photos/<?= htmlspecialchars($foto['foto_url']) ?>" 
                         alt="Foto de <?= htmlspecialchars($user['nombre_completo']) ?>"
                         onclick="abrirModalFoto(this.src)">
                    <?php if ($foto['es_principal']): ?>
                        <span class="foto-badge">Principal</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($es_propio): ?>
        <div style="margin-top: 1rem;">
            <button id="btnSubirFoto" class="btn-edit"><i class="fas fa-plus-circle"></i> Subir nueva foto</button>
        </div>
    <?php endif; ?>
</div>
    </div>

    <div class="info-section">
        <h3><i class="fas fa-comment"></i> Biografia</h3>
        <div style="background: #f8faf5; padding: 1.5rem; border-radius: 1.5rem;">
            <?= nl2br(htmlspecialchars($user['bio'] ?: 'Aún no hay nada escrito en la biografía')) ?>
        </div>
    </div>

    <div style="text-align: center; margin-top: 2rem; font-size: 0.8rem; color: #9aab90;">
        <i class="fas fa-lock"></i> Tus datos están protegidos
    </div>
</div>
</body>
<script>
    function abrirModalFoto(url) {
    const modal = document.createElement('div');
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.backgroundColor = 'rgba(0,0,0,0.9)';
    modal.style.display = 'flex';
    modal.style.justifyContent = 'center';
    modal.style.alignItems = 'center';
    modal.style.zIndex = '2000';
    modal.style.cursor = 'pointer';
    const img = document.createElement('img');
    img.src = url;
    img.style.maxWidth = '90%';
    img.style.maxHeight = '90%';
    img.style.borderRadius = '1rem';
    img.style.boxShadow = '0 0 20px rgba(0,0,0,0.3)';
    modal.appendChild(img);
    modal.onclick = () => modal.remove();
    document.body.appendChild(modal);
}
</script>
</html>