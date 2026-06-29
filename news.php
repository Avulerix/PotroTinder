<?php
// ==============================================
// POTROTINDER - PÁGINA PÚBLICA DE NOVEDADES
// Visible sin necesidad de iniciar sesión
// ==============================================

// Configuración de la base de datos
require_once "Required/conect.php";

// Configurar charset
$conn->set_charset("utf8mb4");

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener total de novedades
$total_sql = "SELECT COUNT(*) as total FROM $news_table";
$total_result = $conn->query($total_sql);
$total_registros = $total_result->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Obtener novedades con paginación (más recientes primero)
$sql = "SELECT id, Fecha, Titulo, Autor, Descripcion 
        FROM $news_table 
        ORDER BY Fecha DESC, id DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $registros_por_pagina, $offset);
$stmt->execute();
$result = $stmt->get_result();

$novedades = [];
while ($row = $result->fetch_assoc()) {
    $novedades[] = $row;
}
$stmt->close();

// Obtener últimas 3 novedades para sidebar
$sidebar_sql = "SELECT id, Titulo, Fecha FROM $news_table ORDER BY Fecha DESC, id DESC LIMIT 3";
$sidebar_result = $conn->query($sidebar_sql);
$ultimas_novedades = [];
while ($row = $sidebar_result->fetch_assoc()) {
    $ultimas_novedades[] = $row;
}

// Obtener año actual para footer
$año_actual = date('Y');

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novedades | PotroTinder - Últimas actualizaciones de la plataforma</title>
    <meta name="description" content="Enterate de las últimas novedades, actualizaciones y eventos de PotroTinder. Mantente informado sobre nuestra comunidad.">
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
            display: flex;
            flex-direction: column;
        }

        /* Header / Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo i {
            font-size: 1.8rem;
            color: rgb(226,43,150);
        }

        .logo h2 {
            color: rgb(33,100,16);
            font-size: 1.5rem;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #4a5c42;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: rgb(226,43,150);
        }

        .btn-registro {
            background: rgb(33,100,16);
            color: white !important;
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            transition: all 0.2s;
        }

        .btn-registro:hover {
            background: #1f6e12;
            transform: translateY(-2px);
        }

        /* Main Container */
        .main-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgb(33,100,16), #2d7a1a);
            border-radius: 2rem;
            padding: 2.5rem;
            margin-bottom: 2.5rem;
            color: white;
            text-align: center;
        }

        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .hero h1 i {
            margin-right: 0.5rem;
        }

        .hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Grid Layout */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 2rem;
        }

        /* Novedades Grid */
        .novedades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .card {
            background: white;
            border-radius: 1.2rem;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }

        .card-header {
            background: linear-gradient(135deg, #f5f9f2, #edf3e8);
            padding: 1.2rem 1.2rem 0.8rem;
            border-bottom: 3px solid rgb(226,43,150);
        }

        .card-header .fecha {
            font-size: 0.75rem;
            color: rgb(226,43,150);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-header h3 {
            font-size: 1.2rem;
            color: #2c3a26;
            margin-top: 0.3rem;
            line-height: 1.3;
        }

        .card-body {
            padding: 1.2rem;
        }

        .autor {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8dc;
            font-size: 0.85rem;
            color: #6c7a64;
        }

        .autor i {
            color: rgb(226,43,150);
        }

        .descripcion {
            color: #4a5c42;
            line-height: 1.5;
            font-size: 0.9rem;
            max-height: 120px;
            overflow-y: auto;
        }

        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: 1.2rem;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            position: sticky;
            top: 90px;
            height: fit-content;
        }

        .sidebar h3 {
            color: rgb(33,100,16);
            margin-bottom: 1rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar h3 i {
            color: rgb(226,43,150);
        }

        .recent-list {
            list-style: none;
        }

        .recent-list li {
            padding: 0.8rem 0;
            border-bottom: 1px solid #e2e8dc;
        }

        .recent-list li:last-child {
            border-bottom: none;
        }

        .recent-list a {
            text-decoration: none;
            color: #4a5c42;
            display: block;
            transition: color 0.2s;
        }

        .recent-list a:hover {
            color: rgb(226,43,150);
        }

        .recent-list .fecha-sm {
            font-size: 0.7rem;
            color: #9aa89b;
            display: block;
            margin-top: 0.2rem;
        }

        .info-box {
            background: #f5f9f2;
            border-radius: 1rem;
            padding: 1rem;
            margin-top: 1.5rem;
            text-align: center;
        }

        .info-box i {
            font-size: 2rem;
            color: rgb(33,100,16);
            margin-bottom: 0.5rem;
        }

        .info-box p {
            font-size: 0.85rem;
            color: #6c7a64;
        }

        .btn-sidebar {
            display: inline-block;
            background: rgb(33,100,16);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.8rem;
            margin-top: 0.8rem;
            transition: background 0.2s;
        }

        .btn-sidebar:hover {
            background: #1f6e12;
        }

        /* Paginación */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            color: #4a5c42;
            background: white;
            border: 1px solid #e2e8dc;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: rgb(33,100,16);
            color: white;
            border-color: rgb(33,100,16);
        }

        .pagination .active {
            background: rgb(33,100,16);
            color: white;
            border-color: rgb(33,100,16);
        }

        /* Empty state */
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

        /* Footer */
        .footer {
            background: white;
            margin-top: 3rem;
            padding: 2rem;
            text-align: center;
            border-top: 1px solid #e2e8dc;
            color: #6c7a64;
            font-size: 0.85rem;
        }

        @media (max-width: 900px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .sidebar {
                position: static;
                order: 2;
            }
            .hero h1 {
                font-size: 1.8rem;
            }
            .nav-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<!-- Barra de navegación pública -->
<div class="navbar">
    <div class="nav-container">
        <div class="logo">
            <i class="fas fa-heart"></i>
            <h2>PotroTinder</h2>
        </div>
        <div class="nav-links">
            <a href="index.html"><i class="fas fa-home"></i> Inicio</a>
            <a href="news.php" style="color: rgb(226,43,150);"><i class="fas fa-newspaper"></i> Novedades</a>
            <a href="registrer.html"><i class="fas fa-user-plus"></i> Registro</a>
            <a href="login.html" class="btn-registro"><i class="fas fa-sign-in-alt"></i> Iniciar sesión</a>
        </div>
    </div>
</div>

<div class="main-container">
    <!-- Hero Section -->
    <div class="hero">
        <h1><i class="fas fa-newspaper"></i> Novedades PotroTinder</h1>
        <p>Entérate de las últimas actualizaciones, eventos y noticias de nuestra comunidad</p>
    </div>

    <div class="content-grid">
        <!-- Sidebar -->
        <div class="sidebar">
            <h3><i class="fas fa-clock"></i> Últimas novedades</h3>
            <?php if (count($ultimas_novedades) > 0): ?>
                <ul class="recent-list">
                    <?php foreach ($ultimas_novedades as $ultima): ?>
                        <li>
                            <a href="?destacado=<?php echo $ultima['id']; ?>#novedad-<?php echo $ultima['id']; ?>">
                                <?php echo htmlspecialchars($ultima['Titulo']); ?>
                                <span class="fecha-sm">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?php echo date('d/m/Y', strtotime($ultima['Fecha'])); ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="color: #9aa89b;">No hay novedades recientes</p>
            <?php endif; ?>
        </div>
        
        
        
        <!-- Columna principal: Novedades -->
        <div>
            <?php if (count($novedades) > 0): ?>
                <div class="novedades-grid">
                    <?php foreach ($novedades as $novedad): ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="fecha">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?php echo date('d/m/Y', strtotime($novedad['Fecha'])); ?>
                                </div>
                                <h3><?php echo htmlspecialchars($novedad['Titulo']); ?></h3>
                            </div>
                            <div class="card-body">
                                <div class="autor">
                                    <i class="fas fa-user-edit"></i>
                                    <span>Por: <?php echo htmlspecialchars($novedad['Autor'] ?: 'Administración'); ?></span>
                                </div>
                                <div class="descripcion">
                                    <?php 
                                    $descripcion = nl2br(htmlspecialchars($novedad['Descripcion']));
                                    echo $descripcion;
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php if ($pagina_actual > 1): ?>
                        <a href="?pagina=1"><i class="fas fa-angle-double-left"></i></a>
                        <a href="?pagina=<?php echo $pagina_actual - 1; ?>"><i class="fas fa-angle-left"></i></a>
                    <?php endif; ?>
                    
                    <?php 
                    $rango = 2;
                    for ($i = 1; $i <= $total_paginas; $i++):
                        if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - $rango && $i <= $pagina_actual + $rango)):
                    ?>
                        <?php if ($i == $pagina_actual): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php 
                        elseif ($i == $pagina_actual - $rango - 1 || $i == $pagina_actual + $rango + 1):
                            echo '<span>...</span>';
                        endif;
                    endfor; 
                    ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                        <a href="?pagina=<?php echo $pagina_actual + 1; ?>"><i class="fas fa-angle-right"></i></a>
                        <a href="?pagina=<?php echo $total_paginas; ?>"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No hay novedades publicadas</h3>
                    <p>Por el momento no hay novedades disponibles. ¡Vuelve pronto!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <p><i class="fas fa-heart" style="color: rgb(226,43,150);"></i> PotroTinder - Conectando personas</p>
    <p style="margin-top: 0.5rem;">&copy; <?php echo $año_actual; ?> PotroTinder. Todos los derechos reservados.</p>
</div>

<!-- Scroll suave para anclajes -->
<script>
    // Para manejar el scroll suave si se usa el hash
    if (window.location.hash) {
        const element = document.querySelector(window.location.hash);
        if (element) {
            setTimeout(() => {
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }
</script>
</body>
</html>