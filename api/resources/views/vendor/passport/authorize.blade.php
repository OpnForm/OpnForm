<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorize – {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ config('app.name') }}</h1>
                <p class="text-gray-500 mt-1">Authorization Request</p>
            </div>

            <p class="text-gray-700 mb-4">
                <strong>{{ $client->name }}</strong> is requesting permission to access your account.
            </p>

            @if (count($scopes) > 0)
                <div class="mb-6">
                    <p class="text-sm font-medium text-gray-700 mb-2">This application will be able to:</p>
                    <ul class="space-y-1">
                        @foreach ($scopes as $scope)
                            <li class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ $scope->description }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex gap-3">
                <form method="post" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit"
                        class="w-full py-2.5 px-4 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        Authorize
                    </button>
                </form>

                <form method="post" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit"
                        class="w-full py-2.5 px-4 bg-white text-gray-700 font-medium rounded-lg border border-gray-300 hover:bg-gray-50 focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 transition-colors">
                        Cancel
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
