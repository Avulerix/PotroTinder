<?php
#Datos Del Servidor
    $locate = "";
    $admin = "";
    $psw = "";
    
#Datos De Base De Datos
    $database = "";
    #Usuarios
		$usr_table = "usuarios";
    #Rastreo
        $track_table = "rastreo";
	#Novedades
		$news_table = "novedades";
	#Version De Sesiones (Seguridad)
		$sess_table = "sessions";


#Conexion Con Base De Datos
    $conn = new mysqli($locate, $admin, $psw, $database);

    if($conn -> connect_error){
        http_response_code(403);
    }

    $conn->set_charset("utf8mb4");

#Mensaje
	$help_mensaje = "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
</head>
<style>
        /* tarjeta principal: glassmorphism + calidez, pero con toques de los nuevos colores */
        .welcome-card {
            background: rgba(255, 255, 250, 0.94);
            backdrop-filter: blur(12px);
            border-radius: 3rem;
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 250, 0.7) inset;
            max-width: 1000px;
            width: 100%;
            padding: 2.5rem 2rem;
            transition: transform 0.3s ease;
            z-index: 2;
            zoom: 50%;
        }

        @media (min-width: 768px) {
            .welcome-card {
                padding: 2.8rem 3rem;
            }
        }
        /* layout interno simplificado: solo texto, botones y pie */
        .content-centered {
            max-width: 700px;
            margin: 0 auto;
            text-align: center;
        }
        .tagline {
            font-size: 1.3rem;
            font-weight: 600;
            color: rgb(33, 100, 16);
            margin-bottom: 1rem;
            background: rgba(33, 100, 16, 0.05);
            display: inline-block;
            padding: 0.2rem 1.2rem;
            border-radius: 60px;
        }

        .description {
            color: #2e3a2a;
            font-weight: 500;
            line-height: 1.55;
            margin: 1.8rem 0 2rem;
            font-size: 1.1rem;
            max-width: 90%;
            margin-left: auto;
            margin-right: auto;
        }
</style>
    <section class='hero'>
        <div class='welcome-card'>
            <div class='content-centered' style='border: 1px solid black; border-radius: 40px; background-color: rgb(226, 43, 150);'>
                <br>
                <div class='tagline' style='color: white;'>
                    APOYANOS EN NUESTRO CRECIMIENTO ;)
                </div>
                <p class='description' style='color: white;'>
                    En <strong>Potro-Tinder</strong> agradecemos tu confianza en nosotros
                    y esperamos que tu experiencia en la plataforma sea agradable
                </p>
                <p class='description' style='color: white;'>
                    Pero ahora necesitamos de tu apoyo para hacer crecer esta comunidad
                    <br>
                    Por eso te pedimos que compartas este proyecto a tus potro-conocidos.
                </p>
                <p class='description' style='color: white;'>
                    Esperamos tu apoyo y colaboracion :)
                </p>
            </div>
        </div>
    </section>
</html>";


// Funcion De Validacion De Version De Session
	function sess_validate($conn, $sess_table) {
        $sql = "SELECT version FROM $sess_table WHERE id = 1";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            return false;
        }
        if (!$stmt->execute()) {
            return false;
        }
        $stmt->bind_result($version);
        if ($stmt->fetch()) {
            return $version;
        }
        return false;
	}
?>
