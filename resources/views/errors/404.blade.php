<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Not found · PlantGrid</title>
    @vite(['resources/css/app.css'])
</head>
<body class="pg-auth">
    <section class="pg-card pg-auth-card">
        <h1 class="pg-page-title">Page not found</h1>
        <p>That address is not part of PlantGrid.</p>
        <a class="btn btn-pg" href="{{ url('/') }}">Go to sign in</a>
    </section>
</body>
</html>
