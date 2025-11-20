<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Models\MessageStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    /**
     * Показати список чатів користувача
     */
    public function index()
    {
        $user = Auth::user();

        $chats = $user->chats()
            ->with(['lastMessage.user', 'participants'])
            ->get()
            ->map(function ($chat) use ($user) {
                // Для приватних чатів показуємо ім'я другого учасника
                if ($chat->type === 'private') {
                    $otherUser = $chat->getOtherParticipant($user->id);
                    $chat->display_name = $otherUser ? $otherUser->name : 'Unknown';
                } else {
                    $chat->display_name = $chat->name;
                }

                // Підрахунок непрочитаних повідомлень
                $chat->unread_count = Message::where('chat_id', $chat->id)
                    ->where('user_id', '!=', $user->id)
                    ->whereDoesntHave('statuses', function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->where('status', 'read');
                    })
                    ->count();

                return $chat;
            });

        return view('chat.index', compact('chats'));
    }

    /**
     * Показати конкретний чат
     */
    public function show($id)
    {
        $user = Auth::user();
        $chat = Chat::with(['participants', 'messages.user', 'messages.statuses'])
            ->findOrFail($id);

        // Перевірка доступу
        if (!$chat->hasParticipant($user->id)) {
            abort(403, 'Access denied');
        }

        // Отримати повідомлення
        $messages = $chat->messages()
            ->with(['user', 'statuses' => function ($query) use ($user) {
                $query->where('user_id', '!=', $user->id);
            }])
            ->orderBy('created_at', 'asc')
            ->get();

        // Позначити всі повідомлення як прочитані
        $this->markMessagesAsRead($chat->id, $user->id);

        // Для приватних чатів
        if ($chat->type === 'private') {
            $otherUser = $chat->getOtherParticipant($user->id);
            $chat->display_name = $otherUser ? $otherUser->name : 'Unknown';
        } else {
            $chat->display_name = $chat->name;
        }

        return view('chat.show', compact('chat', 'messages'));
    }

    /**
     * Створити новий приватний чат
     */
    public function createPrivateChat(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $currentUserId = Auth::id();
        $otherUserId = $request->user_id;

        // Перевірити чи чат вже існує
        $existingChat = Chat::where('type', 'private')
            ->whereHas('participants', function ($query) use ($currentUserId) {
                $query->where('user_id', $currentUserId);
            })
            ->whereHas('participants', function ($query) use ($otherUserId) {
                $query->where('user_id', $otherUserId);
            })
            ->first();

        if ($existingChat) {
            return redirect()->route('chat.show', $existingChat->id);
        }

        // Створити новий чат
        DB::beginTransaction();
        try {
            $chat = Chat::create([
                'type' => 'private',
                'created_by' => $currentUserId
            ]);

            $chat->participants()->attach([$currentUserId, $otherUserId]);

            DB::commit();

            return redirect()->route('chat.show', $chat->id);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create chat');
        }
    }

    /**
     * Відправити повідомлення
     */
    public function sendMessage(Request $request, $chatId)
    {
        $request->validate([
            'message' => 'required|string|max:5000'
        ]);

        $user = Auth::user();
        $chat = Chat::findOrFail($chatId);

        // Перевірка доступу
        if (!$chat->hasParticipant($user->id)) {
            abort(403, 'Access denied');
        }

        DB::beginTransaction();
        try {
            // Створити повідомлення
            $message = Message::create([
                'chat_id' => $chatId,
                'user_id' => $user->id,
                'message' => $request->message
            ]);

            // Створити статуси для всіх учасників (крім відправника)
            $participants = $chat->participants()
                ->where('user_id', '!=', $user->id)
                ->pluck('user_id');

            foreach ($participants as $participantId) {
                MessageStatus::create([
                    'message_id' => $message->id,
                    'user_id' => $participantId,
                    'status' => 'sent',
                    'sent_at' => now()
                ]);
            }

            // Завантажити відношення
            $message->load('user', 'statuses');

            // Опублікувати в Redis для Socket.IO
            $redis = Redis::connection();
            $published = $redis->publish('chat-message', json_encode([
                'chatId' => $chatId,
                'message' => [
                    'id' => $message->id,
                    'user_id' => $message->user_id,
                    'user_name' => $user->name,
                    'message' => $message->message,
                    'created_at' => $message->created_at->toISOString(),
                ]
            ]));

            Log::info('Redis publish chat-message, subscribers: ' . $published);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Send message error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Позначити повідомлення як прочитане
     */
    private function markMessagesAsRead($chatId, $userId)
    {
        $statuses = MessageStatus::whereHas('message', function ($query) use ($chatId) {
            $query->where('chat_id', $chatId);
        })
            ->where('user_id', $userId)
            ->whereIn('status', ['sent', 'delivered'])
            ->get();

        foreach ($statuses as $status) {
            $status->markAsRead();

            // Опублікувати зміну статусу
            $redis = Redis::connection();
            $redis->publish('message-status', json_encode([
                'messageId' => $status->message_id,
                'userId' => $status->message->user_id,
                'status' => 'read'
            ]));

            Log::info('Redis publish message-status for message: ' . $status->message_id);
        }
    }

    /**
     * Список користувачів для створення чату
     */
    public function users()
    {
        $currentUserId = Auth::id();
        $users = User::where('id', '!=', $currentUserId)->get();

        return view('chat.users', compact('users'));
    }
}
