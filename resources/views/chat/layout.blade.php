<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Chat')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <style>
        .message-sent {
            background-color: #3b82f6;
            color: white;
        }
        .message-received {
            background-color: #e5e7eb;
            color: black;
        }
        .online-dot {
            width: 10px;
            height: 10px;
            background-color: #10b981;
            border-radius: 50%;
            display: inline-block;
        }
        .offline-dot {
            width: 10px;
            height: 10px;
            background-color: #6b7280;
            border-radius: 50%;
            display: inline-block;
        }
    </style>
</head>
<body class="bg-gray-100">
<div class="min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">
                <a href="{{ route('chat.index') }}">💬 Chat App</a>
            </h1>
            <div class="flex items-center gap-4">
                <span class="text-gray-600">{{ Auth::user()->name }}</span>
                <a href="{{ route('chat.users') }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    ➕ New Chat
                </a>
            </div>
        </div>
    </header>
    <main class="max-w-7xl mx-auto px-4 py-6">
        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>
