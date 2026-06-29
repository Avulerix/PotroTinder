<?php
// configuracion.php - Ajustes de usuario + gestión de fotos
session_start();
require_once 'Required/conect.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$id = $_SESSION['user_id'];
$mensaje = '';
$error = '';

// Obtener datos actuales del usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Usuario no encontrado.");
}

// Procesar actualización de perfil (datos personales y preferencias)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $nombre = trim($_POST['nombre_completo']);
    $ciudad = trim($_POST['ciudad']);
    $bio = trim($_POST['bio']);
    $busca = $_POST['busca'];
    $buscadogenero = $_POST['buscadogenero'];

    if (empty($nombre)) {
        $error = "El nombre es obligatorio.";
    } else {
        // Subir nueva foto de perfil si se proporciona
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($ext, $allowed)) {
                $archivo = uniqid() . '.' . $ext;
                $ruta = 'Uploads/profile/' . $archivo;
                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $ruta)) {
                    // Eliminar foto anterior si no es la default
                    if ($user['foto_perfil'] !== 'default_avatar.jpg' && file_exists('Uploads/profile/' . $user['foto_perfil'])) {
                        unlink('Uploads/profile/' . $user['foto_perfil']);
                    }
                    $upd_foto = $conn->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                    $upd_foto->bind_param("si", $archivo, $id);
                    $upd_foto->execute();
                    $_SESSION['user_photo'] = $archivo;
                    $mensaje = "Foto de perfil actualizada. ";
                } else {
                    $error = "Error al subir la foto.";
                }
            } else {
                $error = "Formato no permitido. Use JPG, PNG o GIF.";
            }
        }

        if (empty($error)) {
            $upd = $conn->prepare("UPDATE usuarios SET nombre_completo = ?, ciudad = ?, bio = ?, busca = ?, buscadogenero = ? WHERE id = ?");
            $upd->bind_param("sssssi", $nombre, $ciudad, $bio, $busca, $buscadogenero, $id);
            if ($upd->execute()) {
                $mensaje .= "Perfil actualizado correctamente.";
                $_SESSION['user_name'] = $nombre;
                // Recargar datos
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error = "Error al actualizar perfil: " . $conn->error;
            }
        }
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (password_verify($old, $user['contrasena'])) {
        if ($new === $confirm && strlen($new) >= 6) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
            $upd->bind_param("si", $hash, $id);
            if ($upd->execute()) {
                $mensaje = "Contraseña actualizada correctamente.";
            } else {
                $error = "Error al actualizar contraseña.";
            }
        } else {
            $error = "Nueva contraseña no coincide o es muy corta (mínimo 6 caracteres).";
        }
    } else {
        $error = "Contraseña actual incorrecta.";
    }
}

// ========== SECCIÓN CORREGIDA: SUBIR FOTO ADICIONAL ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_foto'])) {
    // Verificar si se subió un archivo sin errores
    if (!isset($_FILES['nueva_foto']) || $_FILES['nueva_foto']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['nueva_foto']['error'] ?? 'sin archivo';
        $error = "Error en la subida del archivo. Código: $error_code";
        // Puedes mapear códigos a mensajes legibles si lo deseas
    } else {
        $ext = strtolower(pathinfo($_FILES['nueva_foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed)) {
            $error = "Formato no permitido. Solo JPG, PNG o GIF.";
        } else {
            // Construir ruta absoluta usando __DIR__ (directorio del script actual)
            $carpeta_fotos = __DIR__ . '/Uploads/photos/';
            // Crear carpeta si no existe (con permisos)
            if (!is_dir($carpeta_fotos)) {
                if (!mkdir($carpeta_fotos, 0755, true)) {
                    $error = "No se pudo crear la carpeta de fotos. Verifica permisos.";
                }
            }
            if (!$error) {
                $nombre_archivo = uniqid() . '.' . $ext;
                $ruta_destino = $carpeta_fotos . $nombre_archivo;
                // Mover archivo
                if (move_uploaded_file($_FILES['nueva_foto']['tmp_name'], $ruta_destino)) {
                    // Insertar en BD (ruta relativa para guardar en BD)
                    $ruta_relativa = $nombre_archivo; // ya que la carpeta es fija
                    $orden = 0;
                    $stmt_insert = $conn->prepare("INSERT INTO fotos_usuario (usuario_id, foto_url, orden) VALUES (?, ?, ?)");
                    $stmt_insert->bind_param("isi", $id, $ruta_relativa, $orden);
                    if ($stmt_insert->execute()) {
                        $mensaje = "Foto subida correctamente.";
                        // Recargar la página para mostrar la nueva foto
                        header("Location: config.php?msg=" . urlencode($mensaje));
                        exit;
                    } else {
                        $error = "Error al guardar en la base de datos: " . $conn->error;
                        // Eliminar archivo físico si falló la BD
                        if (file_exists($ruta_destino)) unlink($ruta_destino);
                    }
                } else {
                    $error = "Error al mover el archivo. Verifica permisos de escritura en la carpeta: " . $carpeta_fotos;
                }
            }
        }
    }
    if ($error) {
        // Guardar error en sesión o redirigir con mensaje
        header("Location: configuracion.php?error=" . urlencode($error));
        exit;
    }
}

// ========== ELIMINAR FOTO ADICIONAL ==========
if (isset($_GET['eliminar_foto']) && is_numeric($_GET['eliminar_foto'])) {
    $foto_id = (int)$_GET['eliminar_foto'];
    // Verificar que la foto pertenezca al usuario
    $check = $conn->prepare("SELECT foto_url FROM fotos_usuario WHERE id = ? AND usuario_id = ?");
    $check->bind_param("ii", $foto_id, $id);
    $check->execute();
    $foto = $check->get_result()->fetch_assoc();
    if ($foto) {
        // Eliminar archivo físico
        $ruta_foto = 'Uploads/photos/' . $foto['foto_url'];
        if (file_exists($ruta_foto)) {
            unlink($ruta_foto);
        }
        // Eliminar registro de BD
        $del = $conn->prepare("DELETE FROM fotos_usuario WHERE id = ?");
        $del->bind_param("i", $foto_id);
        if ($del->execute()) {
            $mensaje = "Foto eliminada correctamente.";
        } else {
            $error = "Error al eliminar la foto.";
        }
    } else {
        $error = "Foto no encontrada o no te pertenece.";
    }
    // Redirigir para evitar reenvío de GET
    header("Location: config.php?msg=" . urlencode($mensaje ?: $error));
    exit;
}

// ========== SECCIÓN: SUBIR NUEVA IDENTIFICACIÓN (PARA DESBLOQUEO) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_identificacion'])) {
    if (isset($_FILES['nueva_identificacion']) && $_FILES['nueva_identificacion']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['nueva_identificacion']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if (in_array($ext, $allowed)) {
            $carpeta_id = __DIR__ . '/Uploads/validate/';
            if (!is_dir($carpeta_id)) {
                mkdir($carpeta_id, 0755, true);
            }
            $nombre_archivo = uniqid() . '.' . $ext;
            $ruta_destino = $carpeta_id . $nombre_archivo;
            
            if (move_uploaded_file($_FILES['nueva_identificacion']['tmp_name'], $ruta_destino)) {
                // Eliminar identificación anterior si existe
                if (!empty($user['foto_identificacion']) && file_exists(__DIR__ . '/Uploads/validate/' . $user['foto_identificacion'])) {
                    unlink(__DIR__ . '/Uploads/validate/' . $user['foto_identificacion']);
                }
                
                // Actualizar BD: nueva identificación y cambiar estado a 'activo' (pendiente de verificación por admin)
                $upd_id = $conn->prepare("UPDATE usuarios SET foto_identificacion = ?, identidad_verificada = 0, estado = 'activo' WHERE id = ?");
                $upd_id->bind_param("si", $nombre_archivo, $id);
                if ($upd_id->execute()) {
                    $mensaje = "Identificación subida correctamente. Tu cuenta ha sido reactivada pendiente de verificación por el administrador.";
                    // Recargar datos del usuario
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = "Error al actualizar la base de datos.";
                    unlink($ruta_destino);
                }
            } else {
                $error = "Error al mover la identificación.";
            }
        } else {
            $error = "Formato no permitido. Use JPG, JPEG o PNG.";
        }
    } else {
        $error = "No se seleccionó ningún archivo o hubo un error en la subida.";
    }
}


// Obtener lista de fotos adicionales del usuario
$stmt_fotos = $conn->prepare("SELECT id, foto_url, es_principal, orden FROM fotos_usuario WHERE usuario_id = ? ORDER BY orden ASC, id ASC");
$stmt_fotos->bind_param("i", $id);
$stmt_fotos->execute();
$fotos_adicionales = $stmt_fotos->get_result()->fetch_all(MYSQLI_ASSOC);

// Mostrar mensajes de éxito/error después de redirección por eliminación
if (isset($_GET['msg'])) {
    $mensaje = htmlspecialchars($_GET['msg']);
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PotroTinder | Configuración</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #fefaf7 0%, #f0f5ec 100%); padding: 2rem; }
        .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 2rem; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        h1 { color: rgb(33,100,16); margin-bottom: 0.5rem; }
        .section-title { color: rgb(226,43,150); font-size: 1.3rem; margin: 1.5rem 0 1rem; border-bottom: 2px solid #f0eef2; padding-bottom: 0.5rem; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #2c3a26; }
        label i { color: rgb(226,43,150); margin-right: 8px; }
        input, select, textarea { width: 100%; padding: 0.8rem 1rem; border: 1.5px solid #e2e8dc; border-radius: 1rem; font-family: 'Inter', sans-serif; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: rgb(226,43,150); }
        .current-photo { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; background: #f8faf5; padding: 1rem; border-radius: 1rem; }
        .current-photo img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid rgb(226,43,150); }
        .btn { background: rgb(33,100,16); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 2rem; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: #1f6e12; transform: translateY(-2px); }
        .btn-secondary { background: #e2e8dc; color: #2c3a26; }
        .mensaje { background: #d4edda; color: #155724; padding: 0.8rem; border-radius: 1rem; margin-bottom: 1rem; }
        .error { background: #f8d7da; color: #721c24; padding: 0.8rem; border-radius: 1rem; margin-bottom: 1rem; }
        hr { margin: 1.5rem 0; border: none; border-top: 1px solid #e2e8dc; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: rgb(33,100,16); text-decoration: none; }

        /* Galería de fotos adicionales */
        .fotos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .foto-item {
            position: relative;
            background: #f0f5ec;
            border-radius: 1rem;
            overflow: hidden;
            aspect-ratio: 1 / 1;
        }
        .foto-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .foto-actions {
            position: absolute;
            bottom: 5px;
            right: 5px;
            display: flex;
            gap: 5px;
        }
        .foto-actions a {
            background: rgba(0,0,0,0.6);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 0.8rem;
        }
        .foto-actions a:hover { background: rgb(226,43,150); }
        .badge-principal {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgb(33,100,16);
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
        }
        @media (max-width: 600px) { body { padding: 1rem; } .container { padding: 1.5rem; } }
    </style>
</head>
<body>
<div class="container">
    <a href="principal.php" class="back-link">&larr; Volver al inicio</a>
    <h1><i class="fas fa-sliders-h"></i> Configuración de cuenta</h1>

    <?php if ($mensaje): ?><div class="mensaje"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Datos personales y foto de perfil (igual que antes) -->
    <div class="current-photo">
        <img src="Uploads/profile/<?= htmlspecialchars($user['foto_perfil']) ?>" alt="Foto actual" onerror="this.src='https://randomuser.me/api/portraits/lego/1.jpg'">
        <div><strong>Foto actual</strong><br><small>Puedes cambiarla abajo</small></div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="section-title"><i class="fas fa-user-circle"></i> Datos personales</div>
        <div class="form-group">
            <label><i class="fas fa-user"></i> Nombre completo</label>
            <input type="text" name="nombre_completo" minlength="8" maxlength="64" value="<?= htmlspecialchars($user['nombre_completo']) ?>" required>
        </div>
        <div class="form-group">
            <label><i class="fas fa-map-marker-alt"></i> Municipio</label>
            <select name="ciudad" value="<?= htmlspecialchars($user['ciudad']) ?>" required>
                <option value="<?= htmlspecialchars($user['ciudad']) ?>"><?= htmlspecialchars($user['ciudad']) ?></option>
                <option value="Acambay">Acambay</option>
                <option value="Acolman">Acolman</option>
                <option value="Aculco">Aculco</option>
                <option value="Almoloya de Alquisiras">Almoloya de Alquisiras</option>
                <option value="Almoloya de Juárez">Almoloya de Juárez</option>
                <option value="Almoloya del Río">Almoloya del Río</option>
                <option value="Amanalco">Amanalco</option>
                <option value="Amatepec">Amatepec</option>
                <option value="Amecameca">Amecameca</option>
                <option value="Apaxco">Apaxco</option>
                <option value="Atenco">Atenco</option>
                <option value="Atizapán">Atizapán</option>
                <option value="Atizapán de Zaragoza">Atizapán de Zaragoza</option>
                <option value="Atlacomulco">Atlacomulco</option>
                <option value="Atlautla">Atlautla</option>
                <option value="Axapusco">Axapusco</option>
                <option value="Ayapango">Ayapango</option>
                <option value="Calimaya">Calimaya</option>
                <option value="Capulhuac">Capulhuac</option>
                <option value="Chalco">Chalco</option>
                <option value="Chapa de Mota">Chapa de Mota</option>
                <option value="Chapultepec">Chapultepec</option>
                <option value="Chiautla">Chiautla</option>
                <option value="Chicoloapan">Chicoloapan</option>
                <option value="Chiconcuac">Chiconcuac</option>
                <option value="Chimalhuacán">Chimalhuacán</option>
                <option value="Coacalco de Berriozábal">Coacalco de Berriozábal</option>
                <option value="Coatepec Harinas">Coatepec Harinas</option>
                <option value="Cocotitlán">Cocotitlán</option>
                <option value="Coyotepec">Coyotepec</option>
                <option value="Cuautitlán">Cuautitlán</option>
                <option value="Cuautitlán Izcalli">Cuautitlán Izcalli</option>
                <option value="Donato Guerra">Donato Guerra</option>
                <option value="Ecatepec de Morelos">Ecatepec de Morelos</option>
                <option value="Ecatzingo">Ecatzingo</option>
                <option value="Huehuetoca">Huehuetoca</option>
                <option value="Hueypoxtla">Hueypoxtla</option>
                <option value="Huixquilucan">Huixquilucan</option>
                <option value="Isidro Fabela">Isidro Fabela</option>
                <option value="Ixtapaluca">Ixtapaluca</option>
                <option value="Ixtapan de la Sal">Ixtapan de la Sal</option>
                <option value="Ixtapan del Oro">Ixtapan del Oro</option>
                <option value="Ixtlahuaca">Ixtlahuaca</option>
                <option value="Jaltenco">Jaltenco</option>
                <option value="Jilotepec">Jilotepec</option>
                <option value="Jilotzingo">Jilotzingo</option>
                <option value="Jiquipilco">Jiquipilco</option>
                <option value="Jocotitlán">Jocotitlán</option>
                <option value="Joquicingo">Joquicingo</option>
                <option value="Juchitepec">Juchitepec</option>
                <option value="La Paz">La Paz</option>
                <option value="Lerma">Lerma</option>
                <option value="Luvianos">Luvianos</option>
                <option value="Malinalco">Malinalco</option>
                <option value="Melchor Ocampo">Melchor Ocampo</option>
                <option value="Metepec">Metepec</option>
                <option value="Mexicaltzingo">Mexicaltzingo</option>
                <option value="Morelos">Morelos</option>
                <option value="Naucalpan de Juárez">Naucalpan de Juárez</option>
                <option value="Nezahualcóyotl">Nezahualcóyotl</option>
                <option value="Nextlalpan">Nextlalpan</option>
                <option value="Nicolás Romero">Nicolás Romero</option>
                <option value="Nopaltepec">Nopaltepec</option>
                <option value="Ocoyoacac">Ocoyoacac</option>
                <option value="Ocuilan">Ocuilan</option>
                <option value="El Oro">El Oro</option>
                <option value="Otumba">Otumba</option>
                <option value="Otzoloapan">Otzoloapan</option>
                <option value="Otzolotepec">Otzolotepec</option>
                <option value="Ozumba">Ozumba</option>
                <option value="Papalotla">Papalotla</option>
                <option value="Polotitlán">Polotitlán</option>
                <option value="Rayón">Rayón</option>
                <option value="San Antonio la Isla">San Antonio la Isla</option>
                <option value="San Felipe del Progreso">San Felipe del Progreso</option>
                <option value="San José del Rincón">San José del Rincón</option>
                <option value="San Martín de las Pirámides">San Martín de las Pirámides</option>
                <option value="San Mateo Atenco">San Mateo Atenco</option>
                <option value="San Simón de Guerrero">San Simón de Guerrero</option>
                <option value="Santo Tomás">Santo Tomás</option>
                <option value="Soyaniquilpan de Juárez">Soyaniquilpan de Juárez</option>
                <option value="Sultepec">Sultepec</option>
                <option value="Tecámac">Tecámac</option>
                <option value="Tejupilco">Tejupilco</option>
                <option value="Temamatla">Temamatla</option>
                <option value="Temascalapa">Temascalapa</option>
                <option value="Temascalcingo">Temascalcingo</option>
                <option value="Temascaltepec">Temascaltepec</option>
                <option value="Temoaya">Temoaya</option>
                <option value="Tenancingo">Tenancingo</option>
                <option value="Tenango del Aire">Tenango del Aire</option>
                <option value="Tenango del Valle">Tenango del Valle</option>
                <option value="Teoloyucan">Teoloyucan</option>
                <option value="Teotihuacán">Teotihuacán</option>
                <option value="Tepetlaoxtoc">Tepetlaoxtoc</option>
                <option value="Tepetlixpa">Tepetlixpa</option>
                <option value="Tepotzotlán">Tepotzotlán</option>
                <option value="Tequixquiac">Tequixquiac</option>
                <option value="Texcaltitlán">Texcaltitlán</option>
                <option value="Texcalyacac">Texcalyacac</option>
                <option value="Texcoco">Texcoco</option>
                <option value="Tezoyuca">Tezoyuca</option>
                <option value="Tianguistenco">Tianguistenco</option>
                <option value="Timilpan">Timilpan</option>
                <option value="Tlalmanalco">Tlalmanalco</option>
                <option value="Tlalnepantla de Baz">Tlalnepantla de Baz</option>
                <option value="Tlatlaya">Tlatlaya</option>
                <option value="Toluca">Toluca</option>
                <option value="Tonatico">Tonatico</option>
                <option value="Tultepec">Tultepec</option>
                <option value="Tultitlán">Tultitlán</option>
                <option value="Valle de Bravo">Valle de Bravo</option>
                <option value="Villa de Allende">Villa de Allende</option>
                <option value="Villa del Carbón">Villa del Carbón</option>
                <option value="Villa Guerrero">Villa Guerrero</option>
                <option value="Villa Victoria">Villa Victoria</option>
                <option value="Xalatlaco">Xalatlaco</option>
                <option value="Xonacatlán">Xonacatlán</option>
                <option value="Zacazonapan">Zacazonapan</option>
                <option value="Zacualpan">Zacualpan</option>
                <option value="Zinacantepec">Zinacantepec</option>
                <option value="Zumpahuacán">Zumpahuacán</option>
                <option value="Zumpango">Zumpango</option>
            </select>
        </div>
        <div class="form-group">
            <label><i class="fas fa-comment"></i> Biografía</label>
            <textarea name="bio" rows="3"><?= htmlspecialchars($user['bio']) ?></textarea>
        </div>
        <div class="form-group">
            <label><i class="fas fa-heart"></i> Busco</label>
            <select name="busca">
                <option value="<?= $user['busca'] == 'relacion' ? 'selected' : '' ?>"> <?= $user['busca'] == 'relacion' ? 'selected' : '' ?> </option>
                <option value="Relacion Seria" <?= $user['busca'] == 'relacion' ? 'selected' : '' ?>>Relación seria</option>
                <option value="Amistad" <?= $user['busca'] == 'amistad' ? 'selected' : '' ?>>Amistad</option>
                <option value="Citas Casuales" <?= $user['busca'] == 'citas_casuales' ? 'selected' : '' ?>>Citas casuales</option>
                <option value="Conocer Gente" <?= $user['busca'] == 'conocer_gente' ? 'selected' : '' ?>>Conocer gente</option>
                <option value="curiosear" <?= $user['busca'] == 'curiosear' ? 'selected' : '' ?>>Curiosear</option>
            </select>
        </div>
        <div class="form-group">
            <label><i class="fas fa-venus-mars"></i> Me interesa conocer a:</label>
            <select name="buscadogenero">
                <option value="H" <?= $user['buscadogenero'] == 'H' ? 'selected' : '' ?>>Hombres</option>
                <option value="M" <?= $user['buscadogenero'] == 'M' ? 'selected' : '' ?>>Mujeres</option>
                <option value="A" <?= $user['buscadogenero'] == 'A' ? 'selected' : '' ?>>Ambos</option>
                <option value="TM" <?= $user['buscadogenero'] == 'TM' ? 'selected' : '' ?>>Therian Mujeres</option>
                <option value="TH" <?= $user['buscadogenero'] == 'TH' ? 'selected' : '' ?>>Therian Hombres</option>
                <option value="AT" <?= $user['buscadogenero'] == 'AT' ? 'selected' : '' ?>>Ambos Therians</option>
                <option value="A" <?= $user['buscadogenero'] == 'A' ? 'selected' : '' ?>>Todos</option>
            </select>
            <small>Esto determina qué perfiles ves en la galería.</small>
        </div>
        <div class="form-group">
            <label><i class="fas fa-camera"></i> Cambiar foto de perfil</label>
            <input type="file" name="foto_perfil" accept="image/jpeg,image/png,image/gif">
        </div>
        <button type="submit" name="actualizar_perfil" class="btn">Guardar cambios</button>
    </form>

    <hr>

    <!-- Sección de fotos adicionales -->
    <div class="section-title"><i class="fas fa-images"></i> Mis fotos adicionales</div>
    <div class="fotos-grid">
        <?php if (empty($fotos_adicionales)): ?>
            <p style="grid-column: 1/-1; color:#7e8c74;">No tienes fotos adicionales. Sube una ahora.</p>
        <?php else: ?>
            <?php foreach ($fotos_adicionales as $foto): ?>
                <div class="foto-item">
                    <img src="Uploads/photos/<?= htmlspecialchars($foto['foto_url']) ?>" alt="Foto adicional" onerror="this.src='https://randomuser.me/api/portraits/lego/1.jpg'">
                    <?php if ($foto['es_principal']): ?>
                        <div class="badge-principal">Principal</div>
                    <?php endif; ?>
                    <div class="foto-actions">
                        <a href="?eliminar_foto=<?= $foto['id'] ?>" onclick="return confirm('¿Eliminar esta foto?')"><i class="fas fa-trash-alt"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Formulario para subir nueva foto -->
    <form method="POST" enctype="multipart/form-data" style="margin-top: 1rem;">
        <div class="form-group">
            <label><i class="fas fa-upload"></i> Subir nueva foto</label>
            <input type="file" name="nueva_foto" accept="image/jpeg,image/png,image/gif" required>
        </div>
        <button type="submit" name="subir_foto" class="btn btn-secondary"><i class="fas fa-plus-circle"></i> Subir foto</button>
    </form>

    <hr>
    
<!-- En config.php, reemplazar la sección de verificación por: -->
<?php if (!$user['identidad_verificada']): ?>
    <hr>
    <div class="section-title"><i class="fas fa-id-card"></i> Verificación de identidad</div>
    <?php if ($user['estado'] === 'bloqueado'): ?>
        <div class="error" style="margin-bottom: 1rem;">
            <i class="fas fa-exclamation-triangle"></i> Tu cuenta está bloqueada por identificación inconsistente. Sube una nueva identificación válida para reactivarla.
        </div>
    <?php elseif (!$user['identidad_verificada'] && $user['foto_identificacion']): ?>
        <div class="mensaje" style="margin-bottom: 1rem; background: #fff3cd; color: #856404;">
            <i class="fas fa-clock"></i> Tu identificación está pendiente de verificación por el administrador. Una vez verificada, esta alerta desaparecerá.
        </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label><i class="fas fa-upload"></i> Subir identificación oficial (INE, pasaporte, cédula)</label>
            <input type="file" name="nueva_identificacion" accept="image/jpeg,image/png" required>
            <small>Formatos permitidos: JPG, JPEG, PNG. Máximo 5MB.</small>
        </div>
        <button type="submit" name="subir_identificacion" class="btn btn-secondary"><i class="fas fa-id-card"></i> Subir identificación</button>
    </form>
<?php else: ?>
    <!-- Usuario verificado: mostrar mensaje de éxito -->
    <hr>
    <div class="section-title"><i class="fas fa-id-card"></i> Verificación de identidad</div>
    <div class="mensaje" style="background: #d4edda; color: #155724;">
        <i class="fas fa-check-circle"></i> Tu identidad ha sido verificada correctamente. ¡Gracias por confiar en nosotros!
    </div>
<?php endif; ?>


    <!-- Cambio de contraseña -->
    <form method="POST">
        <div class="section-title"><i class="fas fa-lock"></i> Seguridad</div>
        <div class="form-group">
            <label><i class="fas fa-key"></i> Contraseña actual</label>
            <input type="password" name="old_password" required>
        </div>
        <div class="form-group">
            <label><i class="fas fa-lock"></i> Nueva contraseña (mínimo 6 caracteres)</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="form-group">
            <label><i class="fas fa-check-circle"></i> Confirmar nueva contraseña</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" name="cambiar_password" class="btn btn-secondary">Cambiar contraseña</button>
    </form>

    <div style="margin-top: 1.5rem; font-size: 0.8rem; text-align: center; color: #9aab90;">
        Miembro desde: <?= date('d/m/Y', strtotime($user['created_at'])) ?>
    </div>

    <div style="margin-top: 1rem; text-align: center; font-size: 0.8rem; color: #9aab90;">
        </i>PotroTinder <br>ESTE PROYECTO NO ESTA ASOCIADO A LA UNIVERSIDAD AUTONOMA DEL ESTADO DE MEXICO
    </div>
</div>
</body>
</html>