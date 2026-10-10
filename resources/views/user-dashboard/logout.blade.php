<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    @include('partials.viewport')
    <title>Logging Out | CURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        setTimeout(function () {
            window.location.href = '{{ route('home') }}';
        }, 3000);
    </script>
</head>

<body class="bg-blue-50 h-screen flex items-center justify-center p-6">

    <div class="max-w-sm w-full bg-white p-8 rounded-3xl shadow-xl text-center">
        <div class="mb-6">
            <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
        </div>

        <h2 class="text-2xl font-extrabold text-gray-800 mb-2">Logging you out...</h2>
        <p class="text-gray-500 mb-6">Thank you for using CURA. Your session is ending securely.</p>

        <div class="text-xs font-bold text-blue-400 uppercase tracking-widest">
            Redirecting to Home
        </div>
    </div>

</body>

</html>