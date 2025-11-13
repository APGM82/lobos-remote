<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu cuenta ha sido creada</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #ffffff;
            color: #51545E;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            width: 100%;
            background-color: #ffffff;
            padding: 20px 0;
        }
        .email-content {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background: #CDD6D0;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #eaeaea;
        }
        .email-header {
            background-color: #AA4465;
            color: #CDD6D0;
            text-align: center;
            padding: 20px 10px;
        }
        .email-body {
            padding: 30px;
            font-size: 16px;
            line-height: 1.6;
        }
        .password-box {
            text-align: center;
            font-size: 18px;
            background: #CDD6D0;
            padding: 12px 18px;
            border-radius: 6px;
            font-weight: bold;
            width: fit-content;
            margin: 10px 0;
        }
        .email-footer {
            text-align: center;
            color: #6b6b6b;
            font-size: 12px;
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="email-wrapper">
    <div class="email-content">

        <div class="email-header">
            <h2>¡Bienvenido a los hombres lobo de Castronegro!</h2>
        </div>

        <div class="email-body">
            <p>Hola <strong>{{ $nickname }}</strong>,</p>

            <p>Tu cuenta ha sido creada correctamente. Puedes iniciar sesión usando la siguiente contraseña temporal:</p>

            <div class="password-box">
                {{ $password }}
            </div>

            <p>Te recomendamos cambiarla al ingresar por primera vez.</p>

            <p>Si no solicitaste esta cuenta, simplemente ignora este correo.</p>

            <br>

            <p>Saludos,<br>
                <strong>El equipo de soporte</strong>
            </p>
        </div>

        <div class="email-footer">
            &copy; {{ date('Y') }} Los 4 Mosqueperros. Todos los derechos reservados.
        </div>

    </div>
</div>

</body>
</html>
