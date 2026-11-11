<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Not allowed · PlantGrid</title>
    @vite(['resources/css/app.css'])
</head>
<body class="pg-auth">
    <section class="pg-card pg-auth-card">
        <h1 class="pg-page-title">Not allowed</h1>
        <p>Your role does not include this part of PlantGrid.</p>
        <a class="btn btn-pg" href="{{ route('dashboard') }}">Back to the dashboard</a>
    </section>
</body>
</html>
