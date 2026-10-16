<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Pika')</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%234f46e5'/%3E%3Cpath fill='white' d='M13.6 3.2a.75.75 0 0 1 .36.85L12.5 10h5.9a.75.75 0 0 1 .55 1.26l-7.9 8.4a.75.75 0 0 1-1.27-.7L11.2 13H5.6a.75.75 0 0 1-.55-1.26l7.6-8.4a.75.75 0 0 1 .95-.14Z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="{{ asset('css/pika.css') }}">
    <script src="{{ asset('js/pika.js') }}" defer></script>
</head>
<body class="@yield('body_class')">
    @yield('body')
</body>
</html>
