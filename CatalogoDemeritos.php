
<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mensaje = "";
$tipoMensaje = "";
$estudiantes = [];
$demeritos = [];

function limpiar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function codigoValido($valor) {
    return filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );
}

try {
    require_once("conexion.php");

    if (!isset($conexion) || !($conexion instanceof mysqli)) {
        throw new Exception("No se encontró la conexión MySQLi.");
    }

    $conexion->set_charset("utf8mb4");

    /*
       IMPORTANTE:
       El docente que otorga el demérito debe
       estar identificado mediante la sesión.

       Ajusta 'cod_docente' si tu sistema de
       inicio de sesión usa otro nombre.
    */

    $codDocenteSesion = codigoValido(
        $_SESSION["cod_docente"] ?? null
    );

    // ======================================
    // GUARDAR O ACTUALIZAR
    // ======================================

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $accion = $_POST["accion"] ?? "";

        if ($accion === "guardar" || $accion === "editar") {

            $nombre = trim($_POST["nombre_demerito"] ?? "");
            $nie = codigoValido($_POST["nie"] ?? null);

            if ($nombre === "" || !$nie) {

                $mensaje = "Complete el nombre del demérito y seleccione un estudiante.";
                $tipoMensaje = "error";

            } elseif (mb_strlen($nombre, "UTF-8") > 500) {

                $mensaje = "El nombre no puede superar los 500 caracteres.";
                $tipoMensaje = "error";

            } elseif (!$codDocenteSesion) {

                $mensaje = "No se pudo identificar al docente. Inicie sesión con una cuenta docente.";
                $tipoMensaje = "error";

            } else {

                // Verificar estudiante

                $sql = "SELECT nie FROM Estudiante WHERE nie = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("i", $nie);
                $stmt->execute();

                $existeEstudiante =
                    $stmt->get_result()->num_rows > 0;

                $stmt->close();

                // Verificar docente de sesión

                $sql = "SELECT cod_docente FROM Docente
                        WHERE cod_docente = ?";

                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("i", $codDocenteSesion);
                $stmt->execute();

                $existeDocente =
                    $stmt->get_result()->num_rows > 0;

                $stmt->close();

                if (!$existeEstudiante || !$existeDocente) {

                    $mensaje = "El estudiante o docente no existe en la base de datos.";
                    $tipoMensaje = "error";

                } elseif ($accion === "guardar") {

                    $sql = "INSERT INTO Demerito
                            (nombre_demerito, nie, cod_docente)
                            VALUES (?, ?, ?)";

                    $stmt = $conexion->prepare($sql);

                    $stmt->bind_param(
                        "sii",
                        $nombre,
                        $nie,
                        $codDocenteSesion
                    );

                    $stmt->execute();
                    $stmt->close();

                    $mensaje = "¡Demérito registrado correctamente!";
                    $tipoMensaje = "exito";

                } else {

                    $codigo = codigoValido(
                        $_POST["cod_demerito"] ?? null
                    );

                    if (!$codigo) {
                        throw new Exception("Código inválido.");
                    }

                    // Solo el docente propietario puede editarlo

                    $sql = "UPDATE Demerito
                            SET nombre_demerito = ?, nie = ?
                            WHERE cod_demerito = ?
                            AND cod_docente = ?";

                    $stmt = $conexion->prepare($sql);

                    $stmt->bind_param(
                        "siii",
                        $nombre,
                        $nie,
                        $codigo,
                        $codDocenteSesion
                    );

                    $stmt->execute();
                    $stmt->close();

                    $mensaje = "Operación de actualización procesada. Verifique el registro.";
                    $tipoMensaje = "exito";
                }
            }
        }

        // ======================================
        // ELIMINAR
        // ======================================

        if ($accion === "eliminar") {

            $codigo = codigoValido(
                $_POST["cod_demerito"] ?? null
            );

            if (!$codigo || !$codDocenteSesion) {

                $mensaje = "No se pudo autorizar la eliminación.";
                $tipoMensaje = "error";

            } else {

                // No eliminar deméritos usados
                // en ControlDemerito

                $sql = "SELECT cod_controldemerito
                        FROM ControlDemerito
                        WHERE cod_demerito = ?
                        LIMIT 1";

                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("i", $codigo);
                $stmt->execute();

                $estaUtilizado =
                    $stmt->get_result()->num_rows > 0;

                $stmt->close();

                if ($estaUtilizado) {

                    $mensaje = "No se puede eliminar: este demérito ya tiene incidencias registradas.";
                    $tipoMensaje = "error";

                } else {

                    $sql = "DELETE FROM Demerito
                            WHERE cod_demerito = ?
                            AND cod_docente = ?";

                    $stmt = $conexion->prepare($sql);

                    $stmt->bind_param(
                        "ii",
                        $codigo,
                        $codDocenteSesion
                    );

                    $stmt->execute();

                    $eliminados = $stmt->affected_rows;
                    $stmt->close();

                    if ($eliminados > 0) {
                        $mensaje = "Demérito eliminado correctamente.";
                        $tipoMensaje = "exito";
                    } else {
                        $mensaje = "No se encontró un demérito autorizado para eliminar.";
                        $tipoMensaje = "error";
                    }
                }
            }
        }
    }

    // ======================================
    // CONSULTAR ESTUDIANTES
    // ======================================

    $resultado = $conexion->query(
        "SELECT nie, nombre, apellido
         FROM Estudiante
         ORDER BY nombre, apellido"
    );

    while ($fila = $resultado->fetch_assoc()) {
        $estudiantes[] = $fila;
    }

    // ======================================
    // CONSULTAR DEMÉRITOS
    // ======================================

    $resultado = $conexion->query(
        "SELECT d.cod_demerito,
                d.nombre_demerito,
                d.nie,
                d.cod_docente,
                e.nombre AS estudiante_nombre,
                e.apellido AS estudiante_apellido
         FROM Demerito d
         LEFT JOIN Estudiante e ON d.nie = e.nie
         ORDER BY d.cod_demerito DESC"
    );

    while ($fila = $resultado->fetch_assoc()) {
        $demeritos[] = $fila;
    }

} catch (Throwable $e) {

    error_log("CatalogoDemeritos: " . $e->getMessage());

    $mensaje = "Ocurrió un error. Revise la conexión, la sesión y las relaciones de la base de datos.";
    $tipoMensaje = "error";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Catálogo de Deméritos</title>

    <link rel="stylesheet"
          href="CatalogoDemeritos.css?v=1">
</head>

<body>

<div class="contenedor">

    <header class="encabezado">
        <div class="icono">📚</div>
        <h1>Catálogo de Deméritos</h1>
        <p>Administración de faltas estudiantiles</p>
    </header>

    <main class="contenido">

        <?php if ($mensaje !== ""): ?>
            <div class="mensaje <?php echo limpiar($tipoMensaje); ?>">
                <?php echo limpiar($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if (!$codDocenteSesion): ?>
            <div class="mensaje aviso">
                Debe iniciar sesión como docente para guardar,
                editar o eliminar deméritos.
                El sistema espera el código en
                $_SESSION["cod_docente"].
            </div>
        <?php endif; ?>

        <section class="tarjeta">

            <h2 id="tituloFormulario">Registrar nuevo demérito</h2>

            <p class="descripcion">
                Complete la información para agregar
                un demérito al sistema.
            </p>

            <form method="POST" id="formulario">

                <input type="hidden"
                       name="accion"
                       id="accion"
                       value="guardar">

                <input type="hidden"
                       name="cod_demerito"
                       id="codigo">

                <div class="grupo">

                    <label>Código del demérito</label>

                    <input type="text"
                           id="codigoVisual"
                           value="Automático"
                           disabled>

                </div>

                <div class="grupo">

                    <label for="nombre">
                        Nombre del demérito
                    </label>

                    <input type="text"
                           name="nombre_demerito"
                           id="nombre"
                           maxlength="500"
                           placeholder="Ej. Llegar tarde a clases"
                           required>

                </div>

                <div class="grupo">

                    <label for="nie">
                        Estudiante asociado
                    </label>

                    <select name="nie" id="nie" required>

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

                <div class="botones">

                    <button type="button"
                            class="btn-limpiar"
                            onclick="limpiarFormulario()">
                        Limpiar
                    </button>

                    <button type="submit"
                            class="btn-guardar"
                            <?php echo !$codDocenteSesion ? "disabled" : ""; ?>>
                        Guardar demérito
                    </button>

                </div>

            </form>

        </section>

        <section class="tarjeta">

            <h2>Deméritos registrados</h2>

            <p class="descripcion">
                Consulte los registros existentes.
            </p>

            <div class="grupo">

                <input type="text"
                       id="buscar"
                       placeholder="Buscar por código, nombre o estudiante...">

            </div>

            <div class="tabla-contenedor">

                <table id="tablaDemeritos">

                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre del demérito</th>
                            <th>Estudiante</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($demeritos as $demerito): ?>

                        <tr>

                            <td>
                                <?php echo limpiar($demerito["cod_demerito"]); ?>
                            </td>

                            <td>
                                <?php echo limpiar($demerito["nombre_demerito"]); ?>
                            </td>

                            <td>
                                <?php
                                echo limpiar(
                                    $demerito["nie"] . " - " .
                                    ($demerito["estudiante_nombre"] ?? "") . " " .
                                    ($demerito["estudiante_apellido"] ?? "")
                                );
                                ?>
                            </td>

                            <td>

                                <?php if (
                                    $codDocenteSesion &&
                                    (string)$codDocenteSesion ===
                                    (string)$demerito["cod_docente"]
                                ): ?>

                                    <div class="acciones">

                                        <button
                                            type="button"
                                            class="btn-editar"
                                            data-codigo="<?php echo limpiar($demerito['cod_demerito']); ?>"
                                            data-nombre="<?php echo limpiar($demerito['nombre_demerito']); ?>"
                                            data-nie="<?php echo limpiar($demerito['nie']); ?>"
                                            onclick="editarDemerito(this)">
                                            Editar
                                        </button>

                                        <form method="POST"
                                              onsubmit="return confirm('¿Seguro que desea eliminar este demérito?');">

                                            <input type="hidden"
                                                   name="accion"
                                                   value="eliminar">

                                            <input type="hidden"
                                                   name="cod_demerito"
                                                   value="<?php echo limpiar($demerito['cod_demerito']); ?>">

                                            <button type="submit"
                                                    class="btn-eliminar">
                                                Eliminar
                                            </button>

                                        </form>

                                    </div>

                                <?php else: ?>

                                    <span class="solo-lectura">
                                        Solo lectura
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <?php if (count($demeritos) === 0): ?>
                <p class="sin-registros">
                    No hay deméritos registrados.
                </p>
            <?php endif; ?>

        </section>

    </main>

    <footer class="pie">
        Sistema de Gestión de Deméritos Estudiantiles
    </footer>

</div>

<script>

// =======================================
// EDITAR DEMÉRITO
// =======================================

function editarDemerito(boton) {

    document.getElementById("accion").value = "editar";

    document.getElementById("codigo").value =
        boton.dataset.codigo;

    document.getElementById("codigoVisual").value =
        boton.dataset.codigo;

    document.getElementById("nombre").value =
        boton.dataset.nombre;

    document.getElementById("nie").value =
        boton.dataset.nie;

    document.getElementById("tituloFormulario").textContent =
        "Editar demérito";

    document.querySelector(".btn-guardar").textContent =
        "Actualizar demérito";

    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
}

// =======================================
// LIMPIAR FORMULARIO
// =======================================

function limpiarFormulario() {

    document.getElementById("formulario").reset();

    document.getElementById("accion").value = "guardar";
    document.getElementById("codigo").value = "";

    document.getElementById("codigoVisual").value =
        "Automático";

    document.getElementById("tituloFormulario").textContent =
        "Registrar nuevo demérito";

    document.querySelector(".btn-guardar").textContent =
        "Guardar demérito";
}

// =======================================
// BUSCAR EN LA TABLA
// =======================================

document.getElementById("buscar")
    .addEventListener("input", function() {

        const texto = this.value.toLowerCase();

        const filas = document.querySelectorAll(
            "#tablaDemeritos tbody tr"
        );

        filas.forEach(fila => {

            const contenido =
                fila.textContent.toLowerCase();

            fila.style.display =
                contenido.includes(texto) ? "" : "none";

        });

    });

</script>

</body>
</html>
