<?php
// ==============================================
// SISTEMA DE REGISTRO DE DOCENTES
// ==============================================

// Mostrar errores durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_OFF);

// Variables
$mensaje = '';
$tipo_alerta = '';
$nombre = '';

try {

    // CONEXIÓN A LA BASE DE DATOS
    require_once('conexion.php');

    // Verificar conexión
    if (!isset($conexion) || !($conexion instanceof mysqli)) {
        throw new Exception("La conexión MySQLi no está disponible.");
    }

    $conexion->set_charset("utf8mb4");

    // ==========================================
    // REGISTRAR DOCENTE
    // ==========================================

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Obtener el nombre del docente
        $nombre = trim($_POST['nombre'] ?? '');

        // Validar que el nombre no esté vacío
        if ($nombre === '') {

            $mensaje = 'Debe escribir el nombre del docente.';
            $tipo_alerta = 'alert-error';

        } elseif (mb_strlen($nombre, 'UTF-8') > 20) {

            $mensaje = 'El nombre del docente no debe superar los 20 caracteres.';
            $tipo_alerta = 'alert-error';

        } else {

            // Guardar el docente en la base de datos
            $sql = "INSERT INTO Docente (nombre) VALUES (?)";

            $stmt = $conexion->prepare($sql);

            if ($stmt) {

                $stmt->bind_param("s", $nombre);

                if ($stmt->execute()) {

                    $mensaje = '¡Docente registrado correctamente!';
                    $tipo_alerta = 'alert-success';

                    // Limpiar el campo después de guardar
                    $nombre = '';

                } else {

                    $mensaje = 'Error al registrar el docente.';
                    $tipo_alerta = 'alert-error';

                    error_log(
                        "Error al registrar docente: " . $stmt->error
                    );
                }

                $stmt->close();

            } else {

                $mensaje = 'No se pudo preparar la consulta.';
                $tipo_alerta = 'alert-error';

                error_log(
                    "Error en la consulta de docente: " .
                    $conexion->error
                );
            }
        }
    }

} catch (Throwable $e) {

    error_log(
        "Error en formulariodocente.php: " . $e->getMessage()
    );

    $mensaje = 'Ocurrió un error al procesar la solicitud. Revise la conexión y la base de datos.';
    $tipo_alerta = 'alert-error';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Registro de docentes</title>

    <style>

        /* =======================================
           ESTILOS GENERALES
        ======================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: linear-gradient(
                135deg,
                #e8eef7,
                #f5f7fb
            );

            min-height: 100vh;
            padding: 40px 15px;
            color: #243247;

            display: flex;
            justify-content: center;
            align-items: center;
        }

        .main-wrapper {
            width: 100%;
            max-width: 500px;
            margin: auto;
        }

        /* =======================================
           CONTENEDOR DEL FORMULARIO
        ======================================= */

        .form-container {
            background: #ffffff;
            padding: 35px;

            border-radius: 16px;

            border-top: 5px solid #17365d;

            box-shadow: 0 10px 30px
                rgba(0, 0, 0, 0.08);
        }

        /* =======================================
           ENCABEZADO
        ======================================= */

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            color: #17365d;
            font-size: 26px;
            margin-bottom: 10px;
        }

        .form-header p {
            color: #667085;
            font-size: 14px;
            line-height: 1.5;
        }

        /* =======================================
           CAMPOS DEL FORMULARIO
        ======================================= */

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 9px;
            color: #344054;
        }

        .form-group input {
            width: 100%;
            padding: 13px 14px;

            border: 1px solid #cbd5e1;
            border-radius: 9px;

            font-size: 14px;
            background: #ffffff;
            color: #243247;

            outline: none;
            transition: 0.2s;
        }

        .form-group input:focus {
            border-color: #2563eb;

            box-shadow: 0 0 0 3px
                rgba(37, 99, 235, 0.12);
        }

        .form-group input::placeholder {
            color: #98a2b3;
        }

        /* =======================================
           BOTÓN DE REGISTRO
        ======================================= */

        .btn-submit {
            width: 100%;
            padding: 14px 20px;

            background: #1764b5;
            color: #ffffff;

            border: none;
            border-radius: 9px;

            font-size: 15px;
            font-weight: 600;

            cursor: pointer;
            transition: 0.2s;
        }

        .btn-submit:hover {
            background: #104d8c;
            transform: translateY(-1px);

            box-shadow: 0 5px 12px
                rgba(23, 100, 181, 0.18);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* =======================================
           MENSAJES DE ÉXITO Y ERROR
        ======================================= */

        .alert {
            padding: 14px;
            margin-bottom: 25px;

            border-radius: 9px;

            font-size: 14px;
            font-weight: 500;
            line-height: 1.5;
        }

        .alert-success {
            background: #e8f7ee;
            color: #166534;
            border: 1px solid #bbebcd;
        }

        .alert-error {
            background: #fff0f0;
            color: #b42318;
            border: 1px solid #f6c6c6;
        }

        /* =======================================
           DISEÑO PARA CELULARES
        ======================================= */

        @media (max-width: 600px) {

            body {
                padding: 20px 12px;
            }

            .form-container {
                padding: 25px 20px;
            }

            .form-header h2 {
                font-size: 23px;
            }

            .form-group input {
                padding: 12px;
            }

            .btn-submit {
                padding: 13px;
            }
        }

    </style>

</head>

<body>

    <div class="main-wrapper">

        <div class="form-container">

            <!-- ENCABEZADO -->

            <div class="form-header">

                <h2>Registro de docentes</h2>

                <p>
                    Formulario para ingresar docentes
                    al sistema de gestión estudiantil.
                </p>

            </div>

            <!-- MENSAJES DE ÉXITO O ERROR -->

            <?php if ($mensaje !== ''): ?>

                <div
                    class="alert <?php echo htmlspecialchars(
                        $tipo_alerta,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    role="alert"
                >

                    <?php echo htmlspecialchars(
                        $mensaje,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </div>

            <?php endif; ?>

            <!-- FORMULARIO DE REGISTRO -->

            <form
                action="formulariodocente.php"
                method="POST"
            >

                <div class="form-group">

                    <label for="nombre">
                        Nombre del docente:
                    </label>

                    <input
                        type="text"
                        name="nombre"
                        id="nombre"
                        maxlength="20"
                        placeholder="Escriba el nombre del docente"
                        value="<?php echo htmlspecialchars(
                            $nombre,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        autocomplete="name"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="btn-submit"
                >
                    Registrar docente
                </button>

            </form>

        </div>

    </div>

</body>

</html>