<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in – {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ config('app.name') }}</h1>
                <p class="text-gray-500 mt-1">Log in to continue</p>
            </div>

            @if (!empty($error))
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-red-700">{{ $error }}</p>
                </div>
            @endif

            <form method="POST" action="{{ url('/oauth/login') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="intended" value="{{ $intended }}">

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" id="email" required autocomplete="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        placeholder="you@example.com"
                        value="{{ old('email') }}">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" id="password" required autocomplete="current-password"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                        placeholder="••••••••">
                </div>

                <button type="submit"
                    class="w-full py-2.5 px-4 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                    Log in
                </button>
            </form>

            <p class="text-xs text-gray-400 text-center mt-6">
                Don't have an account?
                <a href="{{ rtrim(config('app.front_url', config('app.url')), '/') }}/register"
                   target="_blank" class="text-blue-600 hover:underline">
                    Sign up
                </a>
            </p>
        </div>
    </div>
</body>
</html>
