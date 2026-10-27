<?php
// ==============================================
// SISTEMA DE REGISTRO DE DEMÉRITOS ESTUDIANTILES
// ==============================================

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mensaje = "";
$tipoMensaje = "";

$estudiantes = [];
$demeritos = [];
$docentes = [];

$nieSeleccionado = "";
$docenteSeleccionado = "";
$demeritoSeleccionado = "";
$fechaSeleccionada = date("Y-m-d");

function limpiar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

try {

    // CONEXIÓN A LA BASE DE DATOS
    require_once("conexion.php");

    if (!isset($conexion) || !($conexion instanceof mysqli)) {
        throw new Exception(
            "La conexión MySQLi no está disponible."
        );
    }

    $conexion->set_charset("utf8mb4");

    // ==========================================
    // GUARDAR DEMÉRITO
    // ==========================================

    if (
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        isset($_POST["guardar"])
    ) {

        $nieSeleccionado = trim($_POST["nie"] ?? "");
        $docenteSeleccionado = trim(
            $_POST["cod_docente"] ?? ""
        );
        $demeritoSeleccionado = trim(
            $_POST["cod_demerito"] ?? ""
        );
        $fechaSeleccionada = trim(
            $_POST["fecha"] ?? ""
        );

        $nie = filter_var(
            $nieSeleccionado,
            FILTER_VALIDATE_INT
        );

        $cod_docente = filter_var(
            $docenteSeleccionado,
            FILTER_VALIDATE_INT
        );

        $cod_demerito = filter_var(
            $demeritoSeleccionado,
            FILTER_VALIDATE_INT
        );

        $fechaObjeto = DateTime::createFromFormat(
            "!Y-m-d",
            $fechaSeleccionada
        );

        $fechaValida = (
            $fechaObjeto !== false &&
            $fechaObjeto->format("Y-m-d") ===
            $fechaSeleccionada
        );

        if (
            !$nie ||
            !$cod_docente ||
            !$cod_demerito ||
            !$fechaValida
        ) {

            $mensaje = "Por favor, complete correctamente todos los campos.";
            $tipoMensaje = "error";

        } else {

            // VERIFICAR ESTUDIANTE

            $sql = "SELECT nie
                    FROM Estudiante
                    WHERE nie = ?";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $nie);
            $stmt->execute();

            $existeEstudiante =
                $stmt->get_result()->num_rows > 0;

            $stmt->close();

            // VERIFICAR DOCENTE

            $sql = "SELECT cod_docente
                    FROM Docente
                    WHERE cod_docente = ?";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $cod_docente);
            $stmt->execute();

            $existeDocente =
                $stmt->get_result()->num_rows > 0;

            $stmt->close();

            // VERIFICAR DEMÉRITO

            $sql = "SELECT cod_demerito
                    FROM Demerito
                    WHERE cod_demerito = ?";

            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $cod_demerito);
            $stmt->execute();

            $existeDemerito =
                $stmt->get_result()->num_rows > 0;

            $stmt->close();

            // VALIDAR RESULTADOS

            if (!$existeEstudiante) {

                $mensaje = "El estudiante seleccionado no existe.";
                $tipoMensaje = "error";

            } elseif (!$existeDocente) {

                $mensaje = "El docente seleccionado no existe.";
                $tipoMensaje = "error";

            } elseif (!$existeDemerito) {

                $mensaje = "El demérito seleccionado no existe.";
                $tipoMensaje = "error";

            } else {

                // GUARDAR EN CONTROLDEMERITO

                $sql = "INSERT INTO ControlDemerito
                        (
                            fecha,
                            nie,
                            cod_demerito,
                            cod_docente
                        )
                        VALUES (?, ?, ?, ?)";

                $stmt = $conexion->prepare($sql);

                $stmt->bind_param(
                    "siii",
                    $fechaSeleccionada,
                    $nie,
                    $cod_demerito,
                    $cod_docente
                );

                $stmt->execute();

                $codigoGenerado = $conexion->insert_id;

                $stmt->close();

                $mensaje = "¡Demérito registrado correctamente! Código de registro: " .
                           $codigoGenerado;

                $tipoMensaje = "exito";

                // LIMPIAR CAMPOS

                $nieSeleccionado = "";
                $docenteSeleccionado = "";
                $demeritoSeleccionado = "";
                $fechaSeleccionada = date("Y-m-d");

            }
        }
    }

    // ==========================================
    // CARGAR ESTUDIANTES
    // ==========================================

    $sql = "SELECT nie, nombre, apellido
            FROM Estudiante
            ORDER BY nombre ASC, apellido ASC";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $estudiantes[] = $fila;
    }

    // ==========================================
    // CARGAR TODOS LOS DEMÉRITOS
    // ==========================================

    $sql = "SELECT cod_demerito, nombre_demerito
            FROM Demerito
            ORDER BY nombre_demerito ASC";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $demeritos[] = $fila;
    }

    // ==========================================
    // CARGAR DOCENTES
    // ==========================================

    $sql = "SELECT cod_docente, nombre
            FROM Docente
            ORDER BY nombre ASC";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $docentes[] = $fila;
    }

} catch (Throwable $e) {

    error_log(
        "Error RegistroDemerito: " . $e->getMessage()
    );

    $mensaje = "Ocurrió un error al procesar la solicitud. Revise la conexión o las tablas.";
    $tipoMensaje = "error";

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

    <title>Registro de Deméritos</title>

    <!-- ======================================
         CSS INTEGRADO EN EL MISMO PHP
    ======================================= -->

    <style>

        /* =======================================
           REGISTRO DE DEMÉRITOS - DISEÑO
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
        }

        .contenedor {
            max-width: 850px;
            margin: auto;
        }

        /* ENCABEZADO */

        .encabezado {
            background: linear-gradient(
                135deg,
                #17365d,
                #245a96
            );

            color: white;
            text-align: center;
            padding: 35px 25px;
            border-radius: 16px 16px 0 0;
        }

        .icono {
            font-size: 42px;
            margin-bottom: 10px;
        }

        .encabezado h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .encabezado p {
            font-size: 14px;
            color: #dce8f7;
        }

        /* FORMULARIO */

        .formulario {
            background: white;
            padding: 35px;
            border-radius: 0 0 16px 16px;

            box-shadow: 0 10px 30px
                rgba(0, 0, 0, 0.08);
        }

        .formulario h2 {
            color: #17365d;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .descripcion {
            color: #667085;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .grupo {
            margin-bottom: 23px;
        }

        .grupo label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 9px;
            color: #344054;
        }

        .grupo input,
        .grupo select {
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

        .grupo input:focus,
        .grupo select:focus {
            border-color: #2563eb;

            box-shadow: 0 0 0 3px
                rgba(37, 99, 235, 0.12);
        }

        .grupo small {
            display: block;
            color: #667085;
            margin-top: 8px;
            font-size: 12px;
        }

        /* DOS CAMPOS EN UNA FILA */

        .fila {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* BOTONES */

        .botones {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 30px;
        }

        .botones button {
            padding: 13px 24px;
            border: none;
            border-radius: 9px;

            font-size: 14px;
            font-weight: 600;
            cursor: pointer;

            transition: 0.2s;
        }

        .btn-limpiar {
            background: #e9edf3;
            color: #344054;
        }

        .btn-limpiar:hover {
            background: #dce3ed;
        }

        .btn-guardar {
            background: #1764b5;
            color: white;
        }

        .btn-guardar:hover {
            background: #104d8c;
            transform: translateY(-1px);
        }

        /* MENSAJES */

        .mensaje {
            padding: 14px;
            margin-bottom: 25px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 500;
        }

        .exito {
            background: #e8f7ee;
            color: #166534;
            border: 1px solid #bbebcd;
        }

        .error {
            background: #fff0f0;
            color: #b42318;
            border: 1px solid #f6c6c6;
        }

        /* PIE DE PÁGINA */

        .pie {
            text-align: center;
            margin-top: 25px;
            font-size: 13px;
            color: #667085;
        }

        /* RESPONSIVE */

        @media (max-width: 600px) {

            body {
                padding: 15px 10px;
            }

            .encabezado {
                padding: 25px 15px;
            }

            .encabezado h1 {
                font-size: 23px;
            }

            .formulario {
                padding: 22px 17px;
            }

            .fila {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .botones {
                flex-direction: column;
            }

            .botones button {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="contenedor">

    <!-- ENCABEZADO -->

    <div class="encabezado">

        <div class="icono">📋</div>

        <h1>Registro de Deméritos</h1>

        <p>
            Sistema de Gestión de Deméritos Estudiantiles
        </p>

    </div>

    <!-- FORMULARIO -->

    <div class="formulario">

        <h2>Nueva incidencia estudiantil</h2>

        <p class="descripcion">
            Complete los datos para registrar un
            demérito de manera oficial.
        </p>

        <!-- MENSAJES -->

        <?php if ($mensaje !== ""): ?>

            <div class="mensaje <?php echo limpiar($tipoMensaje); ?>">

                <?php echo limpiar($mensaje); ?>

            </div>

        <?php endif; ?>

        <form
            method="POST"
            id="formDemerito"
        >

            <!-- BUSCAR ESTUDIANTE -->

            <div class="grupo">

                <label for="buscar">
                    Buscar estudiante por NIE o nombre
                </label>

                <input
                    type="text"
                    id="buscar"
                    placeholder="Escriba el NIE o nombre del estudiante..."
                    autocomplete="off"
                >

            </div>

            <!-- SELECCIONAR ESTUDIANTE -->

            <div class="grupo">

                <label for="nie">
                    Estudiante
                </label>

                <select
                    name="nie"
                    id="nie"
                    required
                >

                    <option value="">
                        Seleccione un estudiante
                    </option>

                    <?php foreach ($estudiantes as $estudiante): ?>

                        <option
                            value="<?php echo limpiar($estudiante['nie']); ?>"

                            <?php
                            if (
                                (string)$nieSeleccionado ===
                                (string)$estudiante["nie"]
                            ) {
                                echo "selected";
                            }
                            ?>
                        >

                            <?php
                                echo limpiar(
                                    $estudiante["nie"] . " - " .
                                    $estudiante["nombre"] . " " .
                                    $estudiante["apellido"]
                                );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- FECHA Y DOCENTE -->

            <div class="fila">

                <div class="grupo">

                    <label for="fecha">
                        Fecha de la incidencia
                    </label>

                    <input
                        type="date"
                        name="fecha"
                        id="fecha"
                        value="<?php echo limpiar($fechaSeleccionada); ?>"
                        required
                    >

                </div>

                <div class="grupo">

                    <label for="cod_docente">
                        Docente responsable
                    </label>

                    <select
                        name="cod_docente"
                        id="cod_docente"
                        required
                    >

                        <option value="">
                            Seleccione un docente
                        </option>

                        <?php foreach ($docentes as $docente): ?>

                            <option
                                value="<?php echo limpiar($docente['cod_docente']); ?>"

                                <?php
                                if (
                                    (string)$docenteSeleccionado ===
                                    (string)$docente["cod_docente"]
                                ) {
                                    echo "selected";
                                }
                                ?>
                            >

                                <?php
                                    echo limpiar(
                                        $docente["nombre"]
                                    );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

            <!-- DEMÉRITOS -->

            <div class="grupo">

                <label for="cod_demerito">
                    Demérito asociado
                </label>

                <select
                    name="cod_demerito"
                    id="cod_demerito"
                    required
                >

                    <option value="">
                        Seleccione un demérito
                    </option>

                    <?php foreach ($demeritos as $demerito): ?>

                        <option
                            value="<?php echo limpiar($demerito['cod_demerito']); ?>"

                            <?php
                            if (
                                (string)$demeritoSeleccionado ===
                                (string)$demerito["cod_demerito"]
                            ) {
                                echo "selected";
                            }
                            ?>
                        >

                            <?php
                                echo limpiar(
                                    $demerito["nombre_demerito"]
                                );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <small>
                    Seleccione el tipo de falta que desea
                    registrar para el estudiante.
                </small>

            </div>

            <!-- BOTONES -->

            <div class="botones">

                <button
                    type="reset"
                    class="btn-limpiar"
                >
                    Limpiar
                </button>

                <button
                    type="submit"
                    name="guardar"
                    value="1"
                    class="btn-guardar"
                >
                    Guardar demérito
                </button>

            </div>

        </form>

    </div>

    <!-- PIE DE PÁGINA -->

    <div class="pie">

        Sistema de Control de Deméritos Estudiantiles

    </div>

</div>

<!-- JAVASCRIPT -->

<script>

// ==========================================
// BUSCADOR DE ESTUDIANTES
// ==========================================

const buscar = document.getElementById("buscar");

const selectEstudiante =
    document.getElementById("nie");

// Guardar la lista completa de estudiantes

const estudiantesOriginales = Array.from(
    selectEstudiante.options
).slice(1).map(opcion => ({

    value: opcion.value,
    texto: opcion.textContent.trim()

}));

// Buscar por NIE o nombre

buscar.addEventListener("input", function() {

    const texto = this.value
        .toLowerCase()
        .trim();

    const seleccionado = selectEstudiante.value;

    // Limpiar lista visible

    selectEstudiante.innerHTML =
        '<option value="">Seleccione un estudiante</option>';

    let encontrados = 0;

    estudiantesOriginales.forEach(estudiante => {

        if (
            estudiante.texto.toLowerCase().includes(texto) ||
            estudiante.value.includes(texto)
        ) {

            const opcion = new Option(
                estudiante.texto,
                estudiante.value
            );

            selectEstudiante.add(opcion);

            encontrados++;

        }

    });

    // Mostrar aviso si no hay coincidencias

    if (encontrados === 0) {

        const opcion = new Option(
            "No se encontraron estudiantes",
            ""
        );

        opcion.disabled = true;

        selectEstudiante.add(opcion);

    }

    // Mantener la selección si todavía coincide

    const existeSeleccion = Array.from(
        selectEstudiante.options
    ).some(opcion => opcion.value === seleccionado);

    if (existeSeleccion) {
        selectEstudiante.value = seleccionado;
    }

});

// ==========================================
// BOTÓN LIMPIAR
// ==========================================

const formulario =
    document.getElementById("formDemerito");

formulario.addEventListener("reset", function() {

    setTimeout(function() {

        buscar.value = "";

        selectEstudiante.innerHTML =
            '<option value="">Seleccione un estudiante</option>';

        estudiantesOriginales.forEach(estudiante => {

            selectEstudiante.add(
                new Option(
                    estudiante.texto,
                    estudiante.value
                )
            );

        });

    }, 0);

});

</script>

</body>
</html>