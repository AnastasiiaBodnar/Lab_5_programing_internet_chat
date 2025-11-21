@extends('chat.layout')

@section('title', $chat->display_name)

@section('content')
    <div class="bg-white rounded-lg shadow flex flex-col h-[calc(100vh-12rem)]">
        <!-- Chat Header -->
        <div class="p-4 border-b flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('chat.index') }}" class="text-gray-600 hover:text-gray-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h2 class="font-semibold text-lg">{{ $chat->display_name }}</h2>
                    <p class="text-xs text-gray-500">
                        <span id="online-status">Offline</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Messages Container -->
        <div id="messages-container" class="flex-1 overflow-y-auto p-4 space-y-3">
            @foreach($messages as $message)
                <div class="flex {{ $message->user_id == Auth::id() ? 'justify-end' : 'justify-start' }}"
                     data-message-id="{{ $message->id }}"
                     data-user-id="{{ $message->user_id }}">
                    <div class="max-w-xs lg:max-w-md">
                        @if($message->user_id != Auth::id())
                            <p class="text-xs text-gray-500 mb-1">{{ $message->user->name }}</p>
                        @endif
                        <div class="rounded-lg px-4 py-2 {{ $message->user_id == Auth::id() ? 'message-sent' : 'message-received' }}">
                            <p class="break-words">{{ $message->message }}</p>
                            <div class="flex items-center justify-end gap-2 mt-1">
                                <p class="text-xs opacity-75">
                                    {{ $message->created_at->format('H:i') }}
                                </p>
                                @if($message->user_id == Auth::id())
                                    <span class="text-xs message-status" data-message-id="{{ $message->id }}">
                                    @php
                                        $allRead = $message->statuses->every(fn($s) => $s->status === 'read');
                                        $allDelivered = $message->statuses->every(fn($s) => in_array($s->status, ['read', 'delivered']));
                                    @endphp
                                        @if($allRead)
                                            <span style="color: #3b82f6;">&#10003;&#10003;</span>
                                        @elseif($allDelivered)
                                            &#10003;&#10003;
                                        @else
                                            &#10003;
                                        @endif
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Typing Indicator -->
        <div id="typing-indicator" class="px-4 py-2 text-sm text-gray-500 h-8" style="display: none;">
            <span id="typing-user"></span> is typing...
        </div>

        <!-- Message Input -->
        <div class="p-4 border-t">
            <form id="message-form" class="flex gap-2">
                @csrf
                <input
                    type="text"
                    id="message-input"
                    placeholder="Type a message..."
                    class="flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    autocomplete="off"
                >
                <button
                    type="submit"
                    class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition"
                >
                    Send
                </button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const chatId = {{ $chat->id }};
        const userId = {{ Auth::id() }};
        const userName = "{{ Auth::user()->name }}";
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        console.log('Initializing chat:', { chatId, userId, userName });

        const socket = io('http://localhost:3001', {
            transports: ['websocket', 'polling']
        });

        socket.on('connect', function() {
            console.log('Connected to WebSocket');
            socket.emit('authenticate', userId);
            socket.emit('join-chat', chatId);
        });

        socket.on('new-message', function(message) {
            console.log('New message received:', message);
            addMessageToUI(message);
            scrollToBottom();

            if (message.user_id !== userId) {
                console.log('Emitting message-delivered for message:', message.id);
                socket.emit('message-delivered', { messageId: message.id });
            }
        });

        socket.on('message-status-update', function(data) {
            console.log('Status update received:', data);
            updateMessageStatus(data.messageId, data.status);
        });

        socket.on('user-typing', function(data) {
            if (data.userId !== userId) {
                document.getElementById('typing-indicator').style.display = 'block';
                document.getElementById('typing-user').textContent = data.userName;
            }
        });

        socket.on('user-stop-typing', function(data) {
            if (data.userId !== userId) {
                document.getElementById('typing-indicator').style.display = 'none';
            }
        });

        document.getElementById('message-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const input = document.getElementById('message-input');
            const message = input.value.trim();

            if (!message) return;

            try {
                const response = await fetch('/chat/' + chatId + '/message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ message: message })
                });

                const data = await response.json();

                if (data.success) {
                    input.value = '';
                    socket.emit('stop-typing', { chatId: chatId });
                    console.log('Message sent successfully');
                }
            } catch (error) {
                console.error('Error sending message:', error);
            }
        });

        let typingTimeout;
        document.getElementById('message-input').addEventListener('input', function() {
            socket.emit('typing', { chatId: chatId, userName: userName });

            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(function() {
                socket.emit('stop-typing', { chatId: chatId });
            }, 1000);
        });

        function addMessageToUI(message) {
            const container = document.getElementById('messages-container');
            const isMine = message.user_id === userId;

            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex ' + (isMine ? 'justify-end' : 'justify-start');
            messageDiv.setAttribute('data-message-id', message.id);
            messageDiv.setAttribute('data-user-id', message.user_id);

            let html = '<div class="max-w-xs lg:max-w-md">';

            if (!isMine) {
                html += '<p class="text-xs text-gray-500 mb-1">' + message.user_name + '</p>';
            }

            html += '<div class="rounded-lg px-4 py-2 ' + (isMine ? 'message-sent' : 'message-received') + '">';
            html += '<p class="break-words">' + escapeHtml(message.message) + '</p>';
            html += '<div class="flex items-center justify-end gap-2 mt-1">';
            html += '<p class="text-xs opacity-75">' + formatTime(message.created_at) + '</p>';

            if (isMine) {
                html += '<span class="text-xs message-status" data-message-id="' + message.id + '">&#10003;</span>';
            }

            html += '</div></div></div>';

            messageDiv.innerHTML = html;
            container.appendChild(messageDiv);

            if (!isMine) {
                observeMessage(messageDiv);
            }
        }

        function updateMessageStatus(messageId, status) {
            const statusEl = document.querySelector('.message-status[data-message-id="' + messageId + '"]');
            if (statusEl) {
                console.log('Updating message ' + messageId + ' status to ' + status);

                if (status === 'read') {
                    statusEl.innerHTML = '<span style="color: #3b82f6;">&#10003;&#10003;</span>';
                } else if (status === 'delivered') {
                    statusEl.innerHTML = '&#10003;&#10003;';
                } else {
                    statusEl.innerHTML = '&#10003;';
                }
            } else {
                console.warn('Status element not found for message ' + messageId);
            }
        }

        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            container.scrollTop = container.scrollHeight;
        }

        function formatTime(dateString) {
            const date = new Date(dateString);
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            return hours + ':' + minutes;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const messageEl = entry.target;
                    const messageId = messageEl.dataset.messageId;
                    const messageUserId = messageEl.dataset.userId;

                    if (messageUserId !== userId.toString()) {
                        console.log('Message ' + messageId + ' is visible, marking as read');
                        socket.emit('message-read', { messageId: parseInt(messageId) });
                        observer.unobserve(messageEl);
                    }
                }
            });
        }, { threshold: 0.5 });

        function observeMessage(messageEl) {
            const messageUserId = messageEl.dataset.userId;
            if (messageUserId !== userId.toString()) {
                observer.observe(messageEl);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-message-id]').forEach(el => {
                observeMessage(el);
            });
            scrollToBottom();
        });

        scrollToBottom();
    </script>
@endpush
