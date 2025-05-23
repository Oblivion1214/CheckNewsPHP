<?php
include 'config.php';
session_start();

// 1) Verificar sesión
if (!isset($_SESSION['usuarioID'])) {
    header("Location: login.php");
    exit();
}

// 2) Obtener nombre completo
$user_id = $_SESSION['usuarioID'];
$stmt = $connection->prepare("SELECT nombre, apellido_paterno FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}
$user = $res->fetch_assoc();
$nombre_completo = htmlspecialchars($user['nombre'] . ' ' . $user['apellido_paterno'], ENT_QUOTES);
$stmt->close();

// 3) Inicializar variables
$prefill_url       = '';
$prefill_tit       = '';
$prefill_texto     = '';
$prefill_resultado = '';
$error             = '';
$success           = '';

// 4) ¿Es submit final? detectamos si llegó comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario'])) {
    // Leer y validar resultado
    $raw = $_POST['resultado'] ?? '';
    $resultado = ($raw === 'Noticia Verdadera') ? 'Noticia Verdadera' : 'Noticia Falsa';

    // Leer resto de campos
    $usuario_id    = $_SESSION['usuarioID'];
    $noticia_texto = trim($_POST['noticia_texto'] ?? '');
    $categoria     = trim($_POST['categoria']       ?? 'otros');
    $comentario    = trim($_POST['comentario']      ?? '');

    // Validar duplicado
    $chk = $connection->prepare("
      SELECT 1 FROM reportes_noticias_falsas
        WHERE usuario_id = ? AND noticia_texto = ?
      LIMIT 1
    ");
    $chk->bind_param("is", $usuario_id, $noticia_texto);
    $chk->execute();
    $res_chk = $chk->get_result();
    if ($res_chk->num_rows) {
        $error = "Ya has reportado esta noticia anteriormente.";
        $chk->close();
    } else {
        $chk->close();
        // Validar longitudes
        if (mb_strlen($noticia_texto) < 5) {
            $error = "El texto de la noticia es demasiado corto.";
        } elseif (mb_strlen($comentario) < 20) {
            $error = "El comentario debe tener al menos 20 caracteres.";
        } else {
            // Insertar
            $ins = $connection->prepare("
              INSERT INTO reportes_noticias_falsas
                (usuario_id, resultado, noticia_texto, categoria, comentario)
              VALUES (?, ?, ?, ?, ?)
            ");
            $ins->bind_param("issss",
                $usuario_id,
                $resultado,
                $noticia_texto,
                $categoria,
                $comentario
            );
            if ($ins->execute()) {
                $success = "Reporte enviado con éxito. ¡Gracias!";
            } else {
                $error = "Error al guardar el reporte: " . $ins->error;
            }
            $ins->close();
        }
    }
}
// 5) ¿Es prefill? detectamos si llegó noticia_texto sin comentario
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['comentario']) && isset($_POST['noticia_texto'])) {
    $prefill_url       = htmlspecialchars($_POST['noticia_url']   ?? '', ENT_QUOTES);
    $prefill_tit       = htmlspecialchars($_POST['noticia_titulo']?? '', ENT_QUOTES);
    $prefill_texto     = htmlspecialchars($_POST['noticia_texto'] ?? '', ENT_QUOTES);
    // Forzamos mismo valor que usó la IA
    $prefill_resultado = (($_POST['prediccion'] ?? '') === '0' || ($_POST['resultado'] ?? '') === 'Noticia Falsa')
                        ? 'Noticia Falsa'
                        : 'Noticia Verdadera';
}
// 6) GET inicial: nada más

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportar Noticia - Check News</title>
    <style>
    /* Reset y fuente principal */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        background-color: #f8f9fa;
    }

    /* Barra lateral - Mobile First */
    .sidebar {
        width: 100%;
        background-color: #2c3e50;
        color: #ecf0f1;
        padding: 1.5rem 1rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        order: 2; /* Móvil: sidebar después del contenido */
    }

    .logo-container {
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .logo-container img {
        width: 70px;
        height: 70px;
        object-fit: cover;
        margin-bottom: 0.8rem;
        border-radius: 50%;
        border: 2px solid #3498db;
    }

    .sidebar h2 {
        font-size: 1.2rem;
        color: #ecf0f1;
        margin-bottom: 1.5rem;
        font-weight: 600;
    }

    .sidebar ul {
        list-style: none;
        width: 100%;
    }

    .sidebar ul li {
        margin: 0.8rem 0;
    }

    .sidebar ul li a {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        text-decoration: none;
        color: #bdc3c7;
        font-size: 0.9rem;
        padding: 0.6rem 0.8rem;
        border-radius: 0.75rem;
        transition: all 0.3s ease;
    }

    .sidebar ul li a:hover,
    .sidebar ul li a.active {
        background-color: #34495e;
        color: #ecf0f1;
        transform: translateX(5px);
    }

    /* Contenido principal */
    .content,
    .menu-contenido {
        width: 100%;
        padding: 1.5rem;
        background-color: #f8f9fa;
        order: 1; /* Móvil: contenido primero */
    }

    /* User Info */
    .user-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.8rem;
        text-align: right;
        margin-bottom: 1.5rem;
        font-size: 0.9rem;
        color: #7f8c8d;
        flex-wrap: wrap;
    }
    
    .user-info .welcome {
        font-weight: 500;
        color: #2c3e50;
    }
    
    .user-info a {
        color: #3498db;
        text-decoration: none;
        padding: 0.4rem 0.8rem;
        border: 1px solid #3498db;
        border-radius: 1.25rem;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }
    
    .user-info a:hover {
        background-color: #3498db;
        color: white;
    }

    /* Form Container */
    .form-container,
    .search-container {
        background-color: white;
        padding: 1.5rem;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
        width: 100%;
    }

    .form-title,
    .search-container h2 {
        color: #2c3e50;
        font-size: 1.2rem;
        margin-bottom: 1.2rem;
        font-weight: 600;
    }
    
    .search-container p {
        color: #7f8c8d;
        font-size: 0.85rem;
    }

    /* Inputs y botones */
    .form-group,
    .search-bar {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
        margin-bottom: 0.8rem;
    }

    .form-control,
    .search-bar input {
        width: 100%;
        padding: 0.8rem;
        font-size: 0.9rem;
        border: 1px solid #e0e0e0;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
        outline: none;
    }
    
    .form-control:focus,
    .search-bar input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
    }

    .btn-primary,
    .search-bar button {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.8rem;
        font-size: 0.9rem;
        font-weight: 600;
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: #3498db;
        color: white;
        width: 100%;
    }
    
    .btn-primary:hover,
    .search-bar button:hover {
        background-color: #2980b9;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }

    /* Alertas */
    .alert {
        padding: 0.8rem;
        margin-bottom: 1.2rem;
        border-radius: 0.5rem;
        font-size: 0.9rem;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Tablet Styles */
    @media (min-width: 768px) {
        body {
            flex-direction: row;
        }
        
        .sidebar {
            width: 35%;
            max-width: 250px;
            order: 1;
            padding: 1.5rem 1rem;
        }
        
        .content,
        .menu-contenido {
            width: 65%;
            padding: 2rem;
            margin-left: 35%;
        }
        
        .user-info {
            justify-content: flex-end;
            font-size: 1rem;
        }
        
        .user-info a {
            padding: 0.5rem 1rem;
            font-size: 1rem;
        }
        
        .form-group,
        .search-bar {
            flex-direction: row;
        }
        
        .btn-primary,
        .search-bar button {
            width: auto;
        }
    }

    /* Desktop Styles */
    @media (min-width: 1024px) {
        .sidebar {
            width: 20%;
            padding: 2rem 1rem;
        }
        
        .content,
        .menu-contenido {
            width: 80%;
            padding: 2.5rem;
            margin-left: 20%;
        }
        
        .logo-container img {
            width: 90px;
            height: 90px;
        }
        
        .sidebar h2 {
            font-size: 1.5rem;
        }
        
        .sidebar ul li a {
            font-size: 1rem;
            padding: 0.8rem 1rem;
        }
        
        .form-container,
        .search-container {
            padding: 2rem;
            border-radius: 1.5rem;
        }
        
        .form-title,
        .search-container h2 {
            font-size: 1.5rem;
        }
    }
</style>

</head>
<body>
    <!-- Barra lateral -->
    <div class="sidebar">
        <div class="logo-container">
            <img src="CheckNews.png" alt="Logo">
            <h2>CheckNews</h2>
        </div>
        <ul>
            <li><a href="Principal.php"><i class="fas fa-compass"></i> Explorar</a></li>
            <li><a href="verificados.php"><i class="fas fa-check-circle"></i> Noticias reportadas</a></li>
            <li><a href="herramientas.php"><i class="fas fa-tools"></i> Herramientas de Ayuda</a></li>
            <li><a href="reportar.php"><i class="fas fa-flag"></i> Reportar Noticia</a></li>
        </ul>
    </div>

    <!-- Contenido principal -->
    <div class="content">
    <div class="user-info">
      Bienvenido, <?php echo $nombre_completo; ?>
      <a href="logout.php">Cerrar sesión</a>
    </div>

    <div class="form-container">
      <h1 class="form-title">Reportar Noticia Dudosa</h1>

      <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
      <?php elseif ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
      <?php endif; ?>

      <form method="POST" action="reportar.php">
        <!-- Prefill visual -->
        <?php if ($prefill_url): ?>
          <p><strong>URL analizada:</strong>
            <a href="<?php echo $prefill_url; ?>" target="_blank">
              <?php echo $prefill_url; ?>
            </a>
          </p>
        <?php endif; ?>

        <?php if ($prefill_tit): ?>
          <p><strong>Título detectado:</strong> <?php echo $prefill_tit; ?></p>
        <?php endif; ?>

        <?php if ($prefill_resultado): ?>
          <p><strong>Resultado de la IA:</strong> <?php echo $prefill_resultado; ?></p>
        <?php endif; ?>

        <!-- Hidden: para capturar el resultado -->
        <input type="hidden" name="resultado"
              value="<?php echo $prefill_resultado; ?>">
        <!-- Hidden: para distinguir prefill (no hace falta aquí, ya lo procesamos) -->
        <input type="hidden" name="action" value="">

        <!-- Texto/URL de la noticia -->
        <div class="form-group">
          <label for="noticia_texto" class="form-label">
            URL o Texto de la noticia:
          </label>
          <textarea id="noticia_texto" name="noticia_texto"
                    class="form-control" rows="4" required><?php
            // Si ya postearon (validación), muestro POST; si no, muestro prefill
            echo htmlspecialchars(
              $_POST['noticia_texto'] ?? $prefill_texto,
              ENT_QUOTES
            );
          ?></textarea>
        </div>

        <!-- Categoría -->
        <div class="form-group">
          <label for="categoria" class="form-label">Categoría:</label>
          <select id="categoria" name="categoria" class="form-control" required>
            <?php
              $cats = [
                'cancer'=>'Cáncer','diabetes'=>'Diabetes','asma'=>'Asma',
                'hipertension'=>'Hipertensión','obesidad'=>'Obesidad',
                'cardiovasculares'=>'Enfermedades cardiovasculares','otros'=>'Otros'
              ];
              $sel = $_POST['categoria'] ?? '';
              foreach ($cats as $val => $lab) {
                $s = ($sel === $val) ? 'selected' : '';
                echo "<option value=\"$val\" $s>$lab</option>";
              }
            ?>
          </select>
        </div>

        <!-- Comentario -->
        <div class="form-group">
          <label for="comentario" class="form-label">
            ¿Por qué crees que esta noticia es falsa o dudosa?
          </label>
          <textarea id="comentario" name="comentario"
                    class="form-control" rows="5" required><?php
            echo htmlspecialchars($_POST['comentario'] ?? '', ENT_QUOTES);
          ?></textarea>
        </div>
        <small class="text-muted">
            Mínimo 20 caracteres. Describe con detalle tus sospechas.
          </small>

        <button type="submit" class="btn btn-primary">
          <i class="fas fa-flag"></i> Reportar Noticia
        </button>
      </form>
    </div>
  </div>
</body>
</html>