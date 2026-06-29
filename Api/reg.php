<?php
// registrar_process.php
session_start();
require_once '../Required/conect.php'; // Ajusta según tu estructura

// Verificar que se haya enviado por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

// Variables de entorno (similar a Upload.php)
$anterior = $_SERVER['HTTP_REFERER']; // Página de regreso
$carpeta_perfil = "../Uploads/profile/";
$carpeta_id = "../Uploads/validate/";
$extensiones_permitidas = array('png', 'jpg', 'jpeg');

// Función para generar nombre único (igual que en Upload.php)
function generarNombreArchivo($extension) {
    $random = bin2hex(random_bytes(8));
    return $random . '.' . $extension;
}

// Recoger datos del formulario
$nombre = trim($_POST['nombre_completo'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$password = $_POST['password'] ?? '';

$confirm = $_POST['confirm_password'] ?? '';

$fecha_nac = $_POST['fecha_nacimiento'] ?? '';
$genero = $_POST['genero'] ?? 'Oculto';
$busca = $_POST['busca'] ?? '';
$busca_sexo = $_POST['busca_sexo'] ?? 'Oculto';
$ciudad = trim($_POST['ciudad'] ?? 'Desconocido');
$plantel = trim($_POST['plantel'] ?? 'No especificado');
$bio = trim($_POST['bio'] ?? 'Vacio');

$errores = [];

// --- Validaciones de campos ---
if (empty($nombre)) $errores[] = "El nombre completo es obligatorio.";
if (empty($email)) $errores[] = "El email es obligatorio.";
if (empty($password)) $errores[] = "La contraseña es obligatoria.";
if (empty($fecha_nac)) $errores[] = "La fecha de nacimiento es obligatoria.";
if (empty($genero)) $errores[] = "El género es obligatorio.";
if (empty($busca)) $errores[] = "El campo 'Busco' es obligatorio.";
if (empty($busca_sexo)) $errores[] = "El campo 'Me interesa conocer a' es obligatorio.";
if (empty($ciudad)) $errores[] = "El campo 'Municipio' es obligatorio.";
if (empty($plantel)) $errores[] = "El campo 'Plantel' es obligatorio.";
if (empty($bio)) $errores[] = "El campo de tu descripcion es obligatorio.";

if ($password !== $confirm) $errores[] = "Las contraseñas no coinciden.";
if (strlen($password) < 6) $errores[] = "La contraseña debe tener al menos 6 caracteres.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El formato del email no es válido.";

// Después de recoger $fecha_nac y antes de verificar email único
// Validar edad mínima (18 años)
$fecha_nacimiento_obj = DateTime::createFromFormat('Y-m-d', $fecha_nac);
if (!$fecha_nacimiento_obj) {
    $errores[] = "Formato de fecha inválido.";
} else {
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nacimiento_obj)->y;
    if ($edad < 18) {
        $errores[] = "Debes tener al menos 18 años para registrarte.";
    }
}

// Verificar email único en BD
if (empty($errores)) {
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errores[] = "El correo electrónico ya está registrado.";
    $stmt->close();
}

// --- Subida de archivos (con manejo de errores estilo Upload.php) ---
$foto_perfil = null;
$foto_id = null;

// 1. Foto de perfil
if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['foto_perfil']['tmp_name'];
    $nombre_original = $_FILES['foto_perfil']['name'];
    $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensiones_permitidas)) {
        $errores[] = "La foto de perfil debe ser PNG, JPG o JPEG.";
    } else {
        $nombre_final = generarNombreArchivo($ext);
        $ruta = $carpeta_perfil . $nombre_final;
        if (move_uploaded_file($tmp, $ruta)) {
            $foto_perfil = $nombre_final;
        } else {
            $errores[] = "Error al mover la foto de perfil.";
        }
    }
} else {
    $errores[] = "La foto de perfil es obligatoria.";
}

// 2. Identificación
if (isset($_FILES['foto_identificacion']) && $_FILES['foto_identificacion']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['foto_identificacion']['tmp_name'];
    $nombre_original = $_FILES['foto_identificacion']['name'];
    $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensiones_permitidas)) {
        $errores[] = "La identificación debe ser PNG, JPG o JPEG.";
        } else {
        $nombre_final = generarNombreArchivo($ext);
        $ruta = $carpeta_id . $nombre_final;
        if (move_uploaded_file($tmp, $ruta)) {
            $foto_id = $nombre_final;
        } else {
            $errores[] = "Error al mover la identificación.";
        }
    }
} else {
    $errores[] = "La imagen de identificación es obligatoria.";
}

// --- Si hay errores, mostrarlos y detener ---
if (!empty($errores)) {
    echo "<div style='display: flex; flex-direction: column; justify-content: center; align-items: center; font-family: sans-serif; padding: 2rem;'>";
    echo "<h1 style='color: red;'>⚠ Errores en el registro</h1>";
    echo "<ul style='color: #b33; margin-bottom: 2rem;'>";
    foreach ($errores as $err) {
        echo "<li>" . htmlspecialchars($err) . "</li>";
    }
    echo "</ul>";
    echo "<a href='$anterior' style='padding: 12px 24px; border-radius: 11px; background-color: #4CAF50; text-decoration: none; color: white; border: none; font-weight: bold;'>Volver al formulario</a>";
    echo "</div>";
    exit;
}

#TODO FUNCIONA HASTA AQUI ########################################################################################


// --- Insertar en la base de datos ---

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO $usr_table (
    nombre_completo, 
    email, 
    telefono, 
    contrasena, 
    fecha_nacimiento, 
    genero, 
    busca, 
    buscadogenero, 
    bio, 
    ciudad, 
    plantel, 
    foto_perfil, 
    foto_identificacion, 
    identidad_verificada, 
    estado, 
    created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'activo', NOW())";

$stmt = $conn->prepare($sql);

// CORRECCIÓN: bind_param debe tener los tipos y las variables en el ORDEN correcto
// Tipos: sssssssssssss (13 strings: nombre, email, telefono, password, fecha, genero, busca, buscadogenero, bio, ciudad, plantel, foto_perfil, foto_identificacion)
$stmt->bind_param(
    "sssssssssssss",  // 13 strings (todos los campos de texto)
    $nombre,           // 1. nombre_completo
    $email,            // 2. email
    $telefono,         // 3. telefono
    $hashed_password,  // 4. contrasena
    $fecha_nac,        // 5. fecha_nacimiento
    $genero,           // 6. genero
    $busca,            // 7. busca
    $busca_sexo,       // 8. buscadogenero
    $bio,              // 9. bio
    $ciudad,           // 10. ciudad
    $plantel,          // 11. plantel (NUEVO CAMPO)
    $foto_perfil,      // 12. foto_perfil
    $foto_id // 13. foto_identificacion
);



if ($stmt->execute()) {
    // Éxito: mostrar mensaje y enlace al login
    echo "<div style='display: flex; flex-direction: column; justify-content: center; align-items: center; font-family: sans-serif; padding: 2rem;'>";
    echo "<h1 style='color: green;'>✔ Registro exitoso</h1>";
    echo "<p>Tu cuenta ha sido creada correctamente.</p>";
    echo "<a href='../login.html' style='padding: 12px 24px; border-radius: 11px; background-color: #4CAF50; text-decoration: none; color: white; font-weight: bold; margin-top: 1rem;'>Iniciar sesión</a>";
    echo "<br><a href='$anterior' style='margin-top: 1rem; color: #666;'>Volver al registro</a>";
    echo "</div>";
    exit;
} else {
    echo "<div style='display: flex; flex-direction: column; justify-content: center; align-items: center; font-family: sans-serif; padding: 2rem;'>";
    echo "<h1 style='color: red;'>⚠ Error en la base de datos</h1>";
    echo "<p>" . htmlspecialchars($conn->error) . "</p>";
    echo "<a href='$anterior' style='padding: 12px 24px; border-radius: 11px; background-color: #4CAF50; text-decoration: none; color: white; font-weight: bold;'>Volver</a>";
    echo "</div>";
    exit;
}

$stmt->close();
$conn->close();
?>