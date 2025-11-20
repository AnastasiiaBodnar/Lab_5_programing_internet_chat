@extends('chat.layout')

@section('title', 'Select User')

@section('content')
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h2 class="text-xl font-semibold">Start New Chat</h2>
        </div>

        <div class="divide-y">
            @foreach($users as $user)
                <form action="{{ route('chat.create') }}" method="POST" class="p-4 hover:bg-gray-50">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                    <button type="submit" class="w-full text-left flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-800">{{ $user->name }}</h3>
                            <p class="text-sm text-gray-600">{{ $user->email }}</p>
                        </div>
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endsection
