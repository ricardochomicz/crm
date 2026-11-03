<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow">
    <title>Convite para empresa — TelecomHub</title>
</head>

<body>
    <main>
        @if ($isAvailable)
        <h1>Convite para participar de uma empresa</h1>

        <p>Este convite está disponível.</p>

        <p>
            Para aceitar, será necessário entrar ou criar uma conta
            usando o mesmo e-mail que recebeu o convite.
        </p>
        @else
        <h1>Convite indisponível</h1>

        <p>
            Este link é inválido ou o convite não está mais disponível.
        </p>

        <p>
            Se você ainda precisa de acesso, entre em contato
            com o responsável pela empresa.
        </p>
        @endif
    </main>
</body>

</html>