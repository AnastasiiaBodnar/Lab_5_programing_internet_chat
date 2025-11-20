const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const Redis = require('ioredis');
const cors = require('cors');

// Express app
const app = express();
app.use(cors());

const server = http.createServer(app);

// Socket.IO з CORS
const io = new Server(server, {
    cors: {
        origin: "http://localhost:8001",
        methods: ["GET", "POST"],
        credentials: true
    }
});

// Redis клієнти
const redis = new Redis({
    host: 'localhost',
    port: 6379,
    retryStrategy: (times) => {
        const delay = Math.min(times * 50, 2000);
        return delay;
    }
});

const subscriber = redis.duplicate();

// Зберігаємо онлайн користувачів
const onlineUsers = new Map();

// Підписка на Redis канали
subscriber.subscribe('chat-message', 'message-status', (err, count) => {
    if (err) {
        console.error('Failed to subscribe: %s', err.message);
    } else {
        console.log(`Subscribed to ${count} channel(s)`);
    }
});

// Обробка повідомлень з Redis
subscriber.on('message', (channel, message) => {
    console.log(`[Redis] ${channel}: ${message}`);

    try {
        const data = JSON.parse(message);

        switch(channel) {
            case 'chat-message':
                handleChatMessage(data);
                break;
            case 'message-status':
                handleMessageStatus(data);
                break;
        }
    } catch (e) {
        console.error('Error parsing message:', e);
    }
});

// Обробка нового повідомлення
function handleChatMessage(data) {
    const { chatId, message } = data;

    // Відправити всім учасникам чату
    io.to(`chat-${chatId}`).emit('new-message', message);
    console.log(`[Broadcast] Message to chat-${chatId}`);
}

// Обробка зміни статусу повідомлення
function handleMessageStatus(data) {
    const { messageId, userId, status } = data;

    // Відправити відправнику повідомлення
    io.to(`user-${userId}`).emit('message-status-update', {
        messageId,
        status
    });
    console.log(`[Status] Message ${messageId} -> ${status}`);
}

// Socket.IO з'єднання
io.on('connection', (socket) => {
    console.log(`[Connected] Socket: ${socket.id}`);

    // Аутентифікація користувача
    socket.on('authenticate', (userId) => {
        socket.userId = userId;
        socket.join(`user-${userId}`);
        onlineUsers.set(userId, socket.id);

        console.log(`[Auth] User ${userId} authenticated`);

        // Повідомити всім про онлайн статус
        socket.broadcast.emit('user-online', { userId });
    });

    // Приєднання до чату
    socket.on('join-chat', (chatId) => {
        socket.join(`chat-${chatId}`);
        console.log(`[Join] User ${socket.userId} joined chat-${chatId}`);
    });

    // Вихід з чату
    socket.on('leave-chat', (chatId) => {
        socket.leave(`chat-${chatId}`);
        console.log(`[Leave] User ${socket.userId} left chat-${chatId}`);
    });

    // Користувач друкує
    socket.on('typing', ({ chatId, userName }) => {
        socket.to(`chat-${chatId}`).emit('user-typing', {
            userId: socket.userId,
            userName,
            chatId
        });
    });

    // Користувач перестав друкувати
    socket.on('stop-typing', ({ chatId }) => {
        socket.to(`chat-${chatId}`).emit('user-stop-typing', {
            userId: socket.userId,
            chatId
        });
    });

    // Повідомлення доставлено
    socket.on('message-delivered', ({ messageId }) => {
        // Публікуємо в Redis для обробки Laravel
        redis.publish('message-delivered', JSON.stringify({
            messageId,
            userId: socket.userId,
            deliveredAt: new Date().toISOString()
        }));
    });

    // Повідомлення прочитано
    socket.on('message-read', ({ messageId }) => {
        // Публікуємо в Redis для обробки Laravel
        redis.publish('message-read', JSON.stringify({
            messageId,
            userId: socket.userId,
            readAt: new Date().toISOString()
        }));
    });

    // Від'єднання
    socket.on('disconnect', () => {
        if (socket.userId) {
            onlineUsers.delete(socket.userId);
            socket.broadcast.emit('user-offline', { userId: socket.userId });
            console.log(`[Disconnect] User ${socket.userId}`);
        }
    });
});

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        onlineUsers: onlineUsers.size,
        timestamp: new Date().toISOString()
    });
});

// Запуск сервера
const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
    console.log(`✅ Socket.IO server running on port ${PORT}`);
    console.log(`✅ Waiting for Redis messages...`);
});

// Обробка помилок
process.on('uncaughtException', (err) => {
    console.error('Uncaught Exception:', err);
});

process.on('unhandledRejection', (err) => {
    console.error('Unhandled Rejection:', err);
});
