<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen font-[Inter]">
<div class="text-center bg-white p-10 rounded-2xl shadow-md max-w-md w-full">
    <img src="{{asset('404.svg')}}" alt="404 Error" class="mx-auto mb-6 w-60">
{{--    <h1 class="text-4xl font-bold text-gray-800 mb-2">404</h1>--}}
    <p class="text-gray-600 text-lg mb-6">الصفحة التي تبحث عنها غير موجودة</p>
    <a href="{{ url('/') }}" class="inline-block bg-primary text-white px-6 py-2 rounded-full shadow hover:bg-primary/90 transition-all duration-200"
       style="color: #0a0e14">
        العودة إلى الرئيسية
    </a>
</div>
</body>
</html>
