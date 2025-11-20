@extends('chat.layout')

@section('title', 'My Chats')

@section('content')
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b">
            <h2 class="text-xl font-semibold">My Chats</h2>
        </div>

        <div class="divide-y">
            @forelse($chats as $chat)
                <a href="{{ route('chat.show', $chat->id) }}" class="block p-4 hover:bg-gray-50 transition">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-800">{{ $chat->display_name }}</h3>
                                @if($chat->unread_count > 0)
                                    <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                                    {{ $chat->unread_count }}
                                </span>
                                @endif
                            </div>
                            @if($chat->lastMessage)
                                <p class="text-sm text-gray-600 truncate">
                                    <span class="font-medium">{{ $chat->lastMessage->user->name }}:</span>
                                    {{ $chat->lastMessage->message }}
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $chat->lastMessage->created_at->diffForHumans() }}
                                </p>
                            @else
                                <p class="text-sm text-gray-400">No messages yet</p>
                            @endif
                        </div>
                        <div>
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-8 text-center text-gray-500">
                    <p class="mb-4">No chats yet</p>
                    <a href="{{ route('chat.users') }}" class="text-blue-500 hover:underline">
                        Start a new conversation
                    </a>
                </div>
            @endforelse
        </div>
    </div>
@endsection
