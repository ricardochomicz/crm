<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Definir senha — TelecomHub</title>
</head>

<body>
    <main>
        <h1>Definir senha</h1>

        @if (session('status'))
        <p role="status">{{ session('status') }}</p>
        @else
        @if ($errors->any())
        <ul role="alert">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <p>
                <label for="email">E-mail</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required>
            </p>

            <p>
                <label for="password">Nova senha</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="8"
                    maxlength="128"
                    required>
            </p>

            <p>
                <label for="password_confirmation">Confirme a senha</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required>
            </p>

            <button type="submit">Salvar senha</button>
        </form>
        @endif
    </main>
</body>

</html>