<?php
// Configuración de la base de datos
$DB_HOST = getenv("DB_HOST");
$DB_USER = getenv("DB_USER");
$DB_PASSWORD = getenv("DB_PASSWORD");
$DB_NAME = getenv("DB_NAME");
$DB_PORT = getenv("DB_PORT");

// Conexión a la base de datos
$connection = mysqli_connect($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME, $DB_PORT);
if (!$connection) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>
