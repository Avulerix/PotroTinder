<?php
require_once '../Required/conect.php';

// Verificar que se haya enviado el formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "
    	<html lang='es'>
	<head>
		<meta charset='UTF-8'>
		<meta name='viewport' content='width=device-width, initial-scale=1.0'>
		<title>ERROR Desconocido</title>
		<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
	</head>
	<body style='display: flex; align-items: center; justify-content: center; align-self: center; text-align: center; background: linear-gradient(135deg, #fefaf7 0%, #f0f5ec 100%);'>
		<div class='maincont'>
			<h2 class='scont' style='color: red;'>
				<span style='font-size: 220%;'>
					⚠
				</span>
                <br>
                Error Del Servidor
                <br>
                No Asociado A
				<br>
				<a href='../index.html' style='color: rgb(33, 100, 16); text-decoration: none;'>
					<i class='fas fa-heart' style=' color: rgb(226, 43, 150);; font-size: 0.9em;'></i> 
					Potro-Tinder
				</a>
				<br><br>
				Regresa A La Pagina Anterior
				<br>
				Y Reintenta Tu Sesion
			</h2>
			<br>
			<br>
			<p>
				EL REPORTE DE ESTE ERROR SE REALIZA DE MANERA AUTOMATICA
				<br>
				<br>
				SI EL ERROR SE SIGUE PRESENTANDO
				<br>
				<a href='https://t.me/potroadmin' style='color: red;'>Reportalo Dando Click Aqui</a>
				<br>
				CON EL CODIGO
				<span style='color: blue;'>
					N0M3TH	0DP02T
				</span>
			</p>
		</div>
	</body>
	</html>
    	 ";
    exit;
}


$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) ? true : false;

// Validaciones básicas
if (empty($email) || empty($password)) {
    echo "
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
                Tienes Datos Faltantes
                <br>
                <br>
				Regresa A La Pagina Anterior
				<br>
				Y Reintenta Tu Sesion
			</h2>
		</div>
	</body>
	</html>
    	 ";
    exit;
}

// Buscar usuario en la base de datos
$stmt = $conn->prepare("SELECT id, nombre_completo, foto_perfil, contrasena, rol, estado FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Verificar credenciales y estado
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
                Error En Tu Usuario Ó Contraseña
                <br>
                <br>
				Regresa A La Pagina Anterior
				<br>
				Y Reintenta Tu Sesion
			</h2>
		</div>
	</body>
	</html>
        ";
    exit;
}


if (!password_verify($password, $user['contrasena'])) {
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
                Error En Tu Usuario Ó Contraseña
                <br>
                <br>
				Regresa A La Pagina Anterior
				<br>
				Y Reintenta Tu Sesion
			</h2>
		</div>
	</body>
	</html>
        ";
    exit;
}
else{
    // Autenticación exitosa
    
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['nombre_completo'];
    $_SESSION['user_photo'] = $user['foto_perfil'];
    $_SESSION['rol'] = $user['rol'];
    $_SESSION['logged_in'] = true;
    /*
    	$_SESSION['sess_version'] = sess_validate();
	*/
    // Si marcó "Recordarme", extender la duración de la sesión (opcional)
    if ($remember) {
        ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30 días
        session_regenerate_id(true);
    }

    // Actualizar último acceso
    $updateStmt = $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $user['id']);
    $updateStmt->execute();

    
    // RASTREO //
    #Variables Requeridas
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $browser = "Desconocido";
            if (strpos($ua, 'Chrome') !== false && strpos($ua, 'Edg') === false && strpos($ua, 'OPR') === false) {
                $browser = "Chrome";
            } elseif (strpos($ua, 'Firefox') !== false) {
                $browser = "Firefox";
            } elseif (strpos($ua, 'Safari') !== false && strpos($ua, 'Chrome') === false) {
                $browser = "Safari";
            } elseif (strpos($ua, 'Edg') !== false) {
                $browser = "Edge";
            } elseif (strpos($ua, 'MSIE') !== false || strpos($ua, 'Trident') !== false) {
                $browser = "Internet Explorer";
            }

        $ss_usr = $user['nombre_completo'];
        $ss_usr_id = $_SESSION['user_id'];
        $actual = date('Y-m-d H:i:s');
        $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    #Sentencia Y Ejecucion SQL 
        $sqlras = "INSERT INTO $track_table (dispositivo, navegador, usr_ip, usuario, usr_id, momento) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_ras = $conn->prepare($sqlras);
        $stmt_ras->bind_param(
            "ssssis",
            $ua,
            $browser,
            $ip_usuario,
            $ss_usr,
            $ss_usr_id,
            $actual
        );
    #Paro Total Ante Error
    if($stmt_ras->execute()) {
        echo "<script>console.log('Track = OK');</script>";
    }
    else{
        echo "<script>console.log('Track = FALL');</script>";
    }


//////////////
    
    // Redirigir a la página principal
    header('Location: ../principal.php');
    exit;
}



?>