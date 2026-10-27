
<?php
// =============================================
// REGISTRO DE REDENCIONES ESTUDIANTILES
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mensaje = "";
$tipoMensaje = "";

$estudiantes = [];
$incidencias = [];
$actividades = [];
$registros = [];

$fechaHoy = date("Y-m-d");

function limpiar($dato) {
    return htmlspecialchars(
        (string)$dato,
        ENT_QUOTES,
        "UTF-8"
    );
}

function enteroPositivo($valor) {
    return filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );
}

if (empty($_SESSION["csrf_redenciones"])) {
    $_SESSION["csrf_redenciones"] = bin2hex(
        random_bytes(32)
    );
}

try {

    require_once("conexion.php");

    if (!isset($conexion) ||
        !($conexion instanceof mysqli)) {
        throw new Exception("Conexión no disponible.");
    }

    $conexion->set_charset("utf8mb4");

    // =========================================
    // GUARDAR REDENCIÓN
    // =========================================

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        if (!hash_equals(
            $_SESSION["csrf_redenciones"],
            $_POST["csrf"] ?? ""
        )) {
            throw new Exception(
                "Solicitud no válida."
            );
        }

        $nie = enteroPositivo(
            $_POST["nie"] ?? null
        );

        $codControl = enteroPositivo(
            $_POST["cod_controldemerito"] ?? null
        );

        $codRedencion = enteroPositivo(
            $_POST["cod_redencion"] ?? null
        );

        $fechaAsignacion = trim(
            $_POST["fecha_asignacion"] ?? ""
        );

        $fechaInicio = trim(
            $_POST["fecha_inicio"] ?? ""
        );

        $fechaFin = trim(
            $_POST["fecha_fin"] ?? ""
        );

        $fechas = [
            $fechaAsignacion,
            $fechaInicio,
            $fechaFin
        ];

        $fechasValidas = true;

        foreach ($fechas as $fecha) {

            $objeto = DateTime::createFromFormat(
                "!Y-m-d",
                $fecha
            );

            if (
                !$objeto ||
                $objeto->format("Y-m-d") !== $fecha
            ) {
                $fechasValidas = false;
                break;
            }
        }

        if (
            !$nie ||
            !$codControl ||
            !$codRedencion ||
            !$fechasValidas
        ) {

            $mensaje = "Complete correctamente todos los campos.";
            $tipoMensaje = "error";

        } elseif (
            $fechaAsignacion > $fechaInicio ||
            $fechaInicio > $fechaFin
        ) {

            $mensaje = "Las fechas deben seguir este orden: asignación, inicio y finalización.";
            $tipoMensaje = "error";

        } else {

            // VERIFICAR QUE EL DEMÉRITO
            // PERTENEZCA AL ESTUDIANTE

            $sql = "SELECT cod_docente
                    FROM ControlDemerito
                    WHERE cod_controldemerito = ?
                    AND nie = ?";

            $stmt = $conexion->prepare($sql);

            $stmt->bind_param(
                "ii",
                $codControl,
                $nie
            );

            $stmt->execute();

            $resultado = $stmt->get_result();
            $incidencia = $resultado->fetch_assoc();

            $stmt->close();

            // VERIFICAR ACTIVIDAD

            $sql = "SELECT cod_redencion
                    FROM Redencion
                    WHERE cod_redencion = ?";

            $stmt = $conexion->prepare($sql);

            $stmt->bind_param(
                "i",
                $codRedencion
            );

            $stmt->execute();

            $existeActividad =
                $stmt->get_result()->num_rows > 0;

            $stmt->close();

            if (!$incidencia) {

                $mensaje = "El demérito seleccionado no corresponde al estudiante.";
                $tipoMensaje = "error";

            } elseif (!$existeActividad) {

                $mensaje = "La actividad de redención no existe.";
                $tipoMensaje = "error";

            } else {

                // DOCENTE RESPONSABLE
                // DEL DEMÉRITO REGISTRADO

                $codDocente = (int)
                    $incidencia["cod_docente"];

                // VERIFICAR DOCENTE

                $sql = "SELECT cod_docente
                        FROM Docente
                        WHERE cod_docente = ?";

                $stmt = $conexion->prepare($sql);

                $stmt->bind_param(
                    "i",
                    $codDocente
                );

                $stmt->execute();

                $existeDocente =
                    $stmt->get_result()->num_rows > 0;

                $stmt->close();

                if (!$existeDocente) {

                    $mensaje = "No se encontró el docente responsable de esta incidencia.";
                    $tipoMensaje = "error";

                } else {

                    // INSERTAR REDENCIÓN

                    $sql = "INSERT INTO ControlRedencion
                            (
                                cod_redencion,
                                cod_docente,
                                nie,
                                cod_controldemerito,
                                fecha_asignacion,
                                fecha_inicio,
                                fecha_fin
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $conexion->prepare($sql);

                    $stmt->bind_param(
                        "iiiisss",
                        $codRedencion,
                        $codDocente,
                        $nie,
                        $codControl,
                        $fechaAsignacion,
                        $fechaInicio,
                        $fechaFin
                    );

                    $stmt->execute();

                    $codigoNuevo =
                        $conexion->insert_id;

                    $stmt->close();

                    $mensaje = "¡Redención registrada correctamente! Código: " .
                               $codigoNuevo;

                    $tipoMensaje = "exito";
                }
            }
        }
    }

    // =========================================
    // CONSULTAR ESTUDIANTES
    // =========================================

    $sql = "SELECT nie, nombre, apellido
            FROM Estudiante
            ORDER BY nombre, apellido";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $estudiantes[] = $fila;
    }

    // =========================================
    // CONSULTAR INCIDENCIAS DE DEMÉRITOS
    // =========================================

    $sql = "SELECT
                c.cod_controldemerito,
                c.nie,
                c.fecha,
                c.cod_docente,
                d.nombre_demerito
            FROM ControlDemerito c
            INNER JOIN Demerito d
                ON c.cod_demerito = d.cod_demerito
            ORDER BY c.fecha DESC,
                     c.cod_controldemerito DESC";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $incidencias[] = $fila;
    }

    // =========================================
    // CONSULTAR ACTIVIDADES DE REDENCIÓN
    // =========================================

    $sql = "SELECT cod_redencion, nombre
            FROM Redencion
            ORDER BY nombre";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $actividades[] = $fila;
    }

    // =========================================
    // CONSULTAR REDENCIONES REGISTRADAS
    // =========================================

    $sql = "SELECT
                cr.cod_controlredencion,
                cr.fecha_asignacion,
                cr.fecha_inicio,
                cr.fecha_fin,
                e.nie,
                e.nombre AS estudiante,
                e.apellido,
                r.nombre AS actividad,
                d.nombre_demerito,
                doc.nombre AS docente
            FROM ControlRedencion cr

            INNER JOIN Estudiante e
                ON cr.nie = e.nie

            INNER JOIN Redencion r
                ON cr.cod_redencion = r.cod_redencion

            INNER JOIN ControlDemerito cd
                ON cr.cod_controldemerito =
                   cd.cod_controldemerito

            INNER JOIN Demerito d
                ON cd.cod_demerito = d.cod_demerito

            INNER JOIN Docente doc
                ON cr.cod_docente = doc.cod_docente

            ORDER BY cr.cod_controlredencion DESC";

    $resultado = $conexion->query($sql);

    while ($fila = $resultado->fetch_assoc()) {
        $registros[] = $fila;
    }

} catch (Throwable $e) {

    error_log(
        "RegistroRedenciones: " .
        $e->getMessage()
    );

    $mensaje = "Ocurrió un error al procesar la solicitud. Revise la conexión y los registros relacionados.";
    $tipoMensaje = "error";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Registro de Redenciones</title>

    <link rel="stylesheet"
          href="RegistroRedenciones.css?v=1">

</head>

<body>

<div class="contenedor">

    <header class="encabezado">

        <div class="icono">🎓</div>

        <h1>Registro de Redenciones</h1>

        <p>
            Sistema de Gestión de Deméritos
            y Redenciones Estudiantiles
        </p>

    </header>

    <main class="contenido">

        <section class="tarjeta">

            <h2>Asignar nueva redención</h2>

            <p class="descripcion">
                Registre una actividad formativa
                para la redención de un demérito.
            </p>

            <?php if ($mensaje !== ""): ?>

                <div class="mensaje <?php echo limpiar($tipoMensaje); ?>">

                    <?php echo limpiar($mensaje); ?>

                </div>

            <?php endif; ?>

            <form method="POST"
                  id="formRedencion">

                <input type="hidden"
                       name="csrf"
                       value="<?php echo limpiar($_SESSION['csrf_redenciones']); ?>">

                <!-- BUSCADOR -->

                <div class="grupo">

                    <label for="buscar">
                        Buscar estudiante por NIE o nombre
                    </label>

                    <input
                        type="text"
                        id="buscar"
                        placeholder="Escriba el NIE o nombre..."
                        autocomplete="off"
                    >

                </div>

                <!-- ESTUDIANTE -->

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

                            <option value="<?php echo limpiar($estudiante['nie']); ?>">

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

                <!-- DEMÉRITO -->

                <div class="grupo">

                    <label for="cod_controldemerito">
                        Registro del demérito
                    </label>

                    <select
                        name="cod_controldemerito"
                        id="cod_controldemerito"
                        required
                    >

                        <option value="">
                            Seleccione primero un estudiante
                        </option>

                        <?php foreach ($incidencias as $incidencia): ?>

                            <option
                                value="<?php echo limpiar($incidencia['cod_controldemerito']); ?>"
                                data-nie="<?php echo limpiar($incidencia['nie']); ?>"
                            >

                                <?php
                                echo limpiar(
                                    "#" .
                                    $incidencia["cod_controldemerito"] .
                                    " - " .
                                    $incidencia["nombre_demerito"] .
                                    " (" .
                                    $incidencia["fecha"] .
                                    ")"
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <small>
                        Solo aparecerán las incidencias
                        registradas para el estudiante.
                    </small>

                </div>

                <!-- ACTIVIDAD -->

                <div class="grupo">

                    <label for="cod_redencion">
                        Actividad de redención
                    </label>

                    <select
                        name="cod_redencion"
                        id="cod_redencion"
                        required
                    >

                        <option value="">
                            Seleccione una actividad
                        </option>

                        <?php foreach ($actividades as $actividad): ?>

                            <option value="<?php echo limpiar($actividad['cod_redencion']); ?>">

                                <?php echo limpiar($actividad["nombre"]); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <small>
                        Seleccione una actividad educativa,
                        segura y apropiada para el estudiante.
                    </small>

                </div>

                <!-- FECHAS -->

                <div class="fila">

                    <div class="grupo">

                        <label for="fecha_asignacion">
                            Fecha de asignación
                        </label>

                        <input
                            type="date"
                            name="fecha_asignacion"
                            id="fecha_asignacion"
                            value="<?php echo limpiar($fechaHoy); ?>"
                            required
                        >

                    </div>

                    <div class="grupo">

                        <label for="fecha_inicio">
                            Fecha de inicio
                        </label>

                        <input
                            type="date"
                            name="fecha_inicio"
                            id="fecha_inicio"
                            value="<?php echo limpiar($fechaHoy); ?>"
                            required
                        >

                    </div>

                </div>

                <div class="grupo">

                    <label for="fecha_fin">
                        Fecha de finalización
                    </label>

                    <input
                        type="date"
                        name="fecha_fin"
                        id="fecha_fin"
                        value="<?php echo limpiar($fechaHoy); ?>"
                        required
                    >

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
                        class="btn-guardar"
                    >
                        Guardar redención
                    </button>

                </div>

            </form>

        </section>

        <!-- HISTORIAL -->

        <section class="tarjeta">

            <h2>Redenciones registradas</h2>

            <p class="descripcion">
                Consulte las actividades de redención
                asignadas a los estudiantes.
            </p>

            <div class="grupo">

                <input
                    type="text"
                    id="buscarTabla"
                    placeholder="Buscar estudiante, demérito o actividad..."
                >

            </div>

            <div class="tabla-contenedor">

                <table id="tablaRedenciones">

                    <thead>

                        <tr>
                            <th>Código</th>
                            <th>Estudiante</th>
                            <th>Demérito</th>
                            <th>Actividad</th>
                            <th>Docente</th>
                            <th>Asignación</th>
                            <th>Inicio</th>
                            <th>Finalización</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($registros as $registro): ?>

                        <tr>

                            <td>
                                <?php echo limpiar($registro["cod_controlredencion"]); ?>
                            </td>

                            <td>
                                <?php
                                echo limpiar(
                                    $registro["nie"] . " - " .
                                    $registro["estudiante"] . " " .
                                    $registro["apellido"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php echo limpiar($registro["nombre_demerito"]); ?>
                            </td>

                            <td>
                                <?php echo limpiar($registro["actividad"]); ?>
                            </td>

                            <td>
                                <?php echo limpiar($registro["docente"]); ?>
                            </td>

                            <td>
                                <?php echo limpiar($registro["fecha_asignacion"]); ?>
                            </td>

                            <td>
                                <?php echo limpiar($registro["fecha_inicio"]); ?>
                            </td>

                            <td>
                                <?php echo limpiar($registro["fecha_fin"]); ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <?php if (count($registros) === 0): ?>

                <p class="sin-registros">
                    No hay redenciones registradas.
                </p>

            <?php endif; ?>

        </section>

    </main>

    <footer class="pie">
        Sistema de Control de Redenciones Estudiantiles
    </footer>

</div>

<script>

// =========================================
// ELEMENTOS
// =========================================

const buscar = document.getElementById("buscar");
const estudiante = document.getElementById("nie");
const demerito = document.getElementById(
    "cod_controldemerito"
);

// =========================================
// GUARDAR LISTAS ORIGINALES
// =========================================

const listaEstudiantes = Array.from(
    estudiante.options
).slice(1).map(opcion => ({
    valor: opcion.value,
    texto: opcion.textContent.trim()
}));

const listaDemeritos = Array.from(
    demerito.options
).slice(1).map(opcion => ({
    valor: opcion.value,
    texto: opcion.textContent.trim(),
    nie: opcion.dataset.nie
}));

// =========================================
// BUSCAR ESTUDIANTE
// =========================================

buscar.addEventListener("input", function() {

    const texto = this.value.toLowerCase().trim();

    const seleccionado = estudiante.value;

    estudiante.innerHTML =
        '<option value="">Seleccione un estudiante</option>';

    listaEstudiantes.forEach(item => {

        if (
            item.texto.toLowerCase().includes(texto) ||
            item.valor.includes(texto)
        ) {

            estudiante.add(
                new Option(item.texto, item.valor)
            );
        }
    });

    const encontrado = Array.from(
        estudiante.options
    ).some(opcion => opcion.value === seleccionado);

    if (encontrado) {
        estudiante.value = seleccionado;
    }

    actualizarDemeritos();
});

// =========================================
// FILTRAR DEMÉRITOS DEL ESTUDIANTE
// =========================================

function actualizarDemeritos() {

    const nie = estudiante.value;

    demerito.innerHTML =
        '<option value="">Seleccione un demérito</option>';

    if (!nie) {
        demerito.options[0].textContent =
            "Seleccione primero un estudiante";
        return;
    }

    listaDemeritos.forEach(item => {

        if (item.nie === nie) {

            demerito.add(
                new Option(item.texto, item.valor)
            );
        }
    });

    if (demerito.options.length === 1) {
        demerito.options[0].textContent =
            "No hay deméritos registrados para este estudiante";
    }
}

estudiante.addEventListener(
    "change",
    actualizarDemeritos
);

// =========================================
// VALIDAR FECHAS
// =========================================

document.getElementById("formRedencion")
.addEventListener("submit", function(event) {

    const asignacion = document.getElementById(
        "fecha_asignacion"
    ).value;

    const inicio = document.getElementById(
        "fecha_inicio"
    ).value;

    const fin = document.getElementById(
        "fecha_fin"
    ).value;

    if (asignacion > inicio || inicio > fin) {

        event.preventDefault();

        alert(
            "Verifique las fechas: asignación, inicio y finalización deben estar en orden."
        );
    }
});

// =========================================
// LIMPIAR
// =========================================

document.getElementById("formRedencion")
.addEventListener("reset", function() {

    setTimeout(() => {

        buscar.value = "";

        estudiante.innerHTML =
            '<option value="">Seleccione un estudiante</option>';

        listaEstudiantes.forEach(item => {

            estudiante.add(
                new Option(item.texto, item.valor)
            );
        });

        actualizarDemeritos();

    }, 0);
});

// =========================================
// BUSCAR EN HISTORIAL
// =========================================

document.getElementById("buscarTabla")
.addEventListener("input", function() {

    const texto = this.value.toLowerCase();

    const filas = document.querySelectorAll(
        "#tablaRedenciones tbody tr"
    );

    filas.forEach(fila => {

        fila.style.display =
            fila.textContent.toLowerCase().includes(texto)
                ? ""
                : "none";

    });
});

actualizarDemeritos();

</script>

</body>
</html>
