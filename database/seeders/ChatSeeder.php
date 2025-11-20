<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use App\Models\MessageStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::create([
            'name' => 'Alice',
            'email' => 'alice@test.com',
            'password' => Hash::make('password'),
        ]);

        $user2 = User::create([
            'name' => 'Bob',
            'email' => 'bob@test.com',
            'password' => Hash::make('password'),
        ]);

        $user3 = User::create([
            'name' => 'Charlie',
            'email' => 'charlie@test.com',
            'password' => Hash::make('password'),
        ]);

        $chat1 = Chat::create([
            'type' => 'private',
            'created_by' => $user1->id,
        ]);
        $chat1->participants()->attach([$user1->id, $user2->id]);

        $msg1 = Message::create([
            'chat_id' => $chat1->id,
            'user_id' => $user1->id,
            'message' => 'Hey Bob! How are you?',
            'created_at' => now()->subMinutes(10),
        ]);

        MessageStatus::create([
            'message_id' => $msg1->id,
            'user_id' => $user2->id,
            'status' => 'read',
            'sent_at' => now()->subMinutes(10),
            'delivered_at' => now()->subMinutes(9),
            'read_at' => now()->subMinutes(8),
        ]);

        $msg2 = Message::create([
            'chat_id' => $chat1->id,
            'user_id' => $user2->id,
            'message' => 'Hi Alice! I\'m doing great, thanks!',
            'created_at' => now()->subMinutes(8),
        ]);

        MessageStatus::create([
            'message_id' => $msg2->id,
            'user_id' => $user1->id,
            'status' => 'read',
            'sent_at' => now()->subMinutes(8),
            'delivered_at' => now()->subMinutes(7),
            'read_at' => now()->subMinutes(7),
        ]);

        $msg3 = Message::create([
            'chat_id' => $chat1->id,
            'user_id' => $user1->id,
            'message' => 'That\'s awesome! Want to grab coffee later?',
            'created_at' => now()->subMinutes(5),
        ]);

        MessageStatus::create([
            'message_id' => $msg3->id,
            'user_id' => $user2->id,
            'status' => 'delivered',
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now()->subMinutes(4),
        ]);

        $chat2 = Chat::create([
            'type' => 'private',
            'created_by' => $user1->id,
        ]);
        $chat2->participants()->attach([$user1->id, $user3->id]);

        $msg4 = Message::create([
            'chat_id' => $chat2->id,
            'user_id' => $user3->id,
            'message' => 'Hey Alice, long time no see!',
            'created_at' => now()->subHours(2),
        ]);

        MessageStatus::create([
            'message_id' => $msg4->id,
            'user_id' => $user1->id,
            'status' => 'sent',
            'sent_at' => now()->subHours(2),
        ]);

        $chat3 = Chat::create([
            'type' => 'group',
            'name' => 'Friends Group',
            'created_by' => $user1->id,
        ]);
        $chat3->participants()->attach([$user1->id, $user2->id, $user3->id]);

        $msg5 = Message::create([
            'chat_id' => $chat3->id,
            'user_id' => $user1->id,
            'message' => 'Welcome to the group, everyone!',
            'created_at' => now()->subDays(1),
        ]);

        foreach ([$user2->id, $user3->id] as $userId) {
            MessageStatus::create([
                'message_id' => $msg5->id,
                'user_id' => $userId,
                'status' => 'read',
                'sent_at' => now()->subDays(1),
                'delivered_at' => now()->subDays(1)->addMinutes(5),
                'read_at' => now()->subDays(1)->addMinutes(10),
            ]);
        }

        $this->command->info(' Test data created successfully!');
        $this->command->info(' Users:');
        $this->command->info('   - alice@test.com / password');
        $this->command->info('   - bob@test.com / password');
        $this->command->info('   - charlie@test.com / password');
    }
}
