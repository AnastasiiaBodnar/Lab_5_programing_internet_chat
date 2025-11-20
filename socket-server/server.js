const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const Redis = require('ioredis');
const cors = require('cors');

const app = express();
app.use(cors());

const server = http.createServer(app);

const io = new Server(server, {
    cors: {
        origin: "http://localhost:8001",
        methods: ["GET", "POST"],
        credentials: true
    }
});

const redis = new Redis({
    host: 'localhost',
    port: 6379,
    retryStrategy: (times) => {
        const delay = Math.min(times * 50, 2000);
        return delay;
    }
});

const subscriber = redis.duplicate();

const onlineUsers = new Map();

subscriber.subscribe('chat-message', 'message-status', (err, count) => {
    if (err) {
        console.error('Failed to subscribe: %s', err.message);
    } else {
        console.log(`Subscribed to ${count} channel(s)`);
    }
});

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

function handleChatMessage(data) {
    const { chatId, message } = data;

    io.to(`chat-${chatId}`).emit('new-message', message);
    console.log(`[Broadcast] Message to chat-${chatId}`);
}

function handleMessageStatus(data) {
    const { messageId, userId, status } = data;

    // Відправити відправнику повідомлення
    io.to(`user-${userId}`).emit('message-status-update', {
        messageId,
        status
    });
    console.log(`[Status] Message ${messageId} -> ${status}`);
}

io.on('connection', (socket) => {
    console.log(`[Connected] Socket: ${socket.id}`);

    // Аутентифікація користувача
    socket.on('authenticate', (userId) => {
        socket.userId = userId;
        socket.join(`user-${userId}`);
        onlineUsers.set(userId, socket.id);

        console.log(`[Auth] User ${userId} authenticated`);

        socket.broadcast.emit('user-online', { userId });
    });

    socket.on('join-chat', (chatId) => {
        socket.join(`chat-${chatId}`);
        console.log(`[Join] User ${socket.userId} joined chat-${chatId}`);
    });

    socket.on('leave-chat', (chatId) => {
        socket.leave(`chat-${chatId}`);
        console.log(`[Leave] User ${socket.userId} left chat-${chatId}`);
    });

    socket.on('typing', ({ chatId, userName }) => {
        socket.to(`chat-${chatId}`).emit('user-typing', {
            userId: socket.userId,
            userName,
            chatId
        });
    });

    socket.on('stop-typing', ({ chatId }) => {
        socket.to(`chat-${chatId}`).emit('user-stop-typing', {
            userId: socket.userId,
            chatId
        });
    });

    socket.on('message-delivered', ({ messageId }) => {
        // Публікуємо в Redis для обробки Laravel
        redis.publish('message-delivered', JSON.stringify({
            messageId,
            userId: socket.userId,
            deliveredAt: new Date().toISOString()
        }));
    });

    socket.on('message-read', ({ messageId }) => {
        // Публікуємо в Redis для обробки Laravel
        redis.publish('message-read', JSON.stringify({
            messageId,
            userId: socket.userId,
            readAt: new Date().toISOString()
        }));
    });

    socket.on('disconnect', () => {
        if (socket.userId) {
            onlineUsers.delete(socket.userId);
            socket.broadcast.emit('user-offline', { userId: socket.userId });
            console.log(`[Disconnect] User ${socket.userId}`);
        }
    });
});

app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        onlineUsers: onlineUsers.size,
        timestamp: new Date().toISOString()
    });
});

const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
    console.log(` Socket.IO server running on port ${PORT}`);
    console.log(`Waiting for Redis messages...`);
});

process.on('uncaughtException', (err) => {
    console.error('Uncaught Exception:', err);
});

process.on('unhandledRejection', (err) => {
    console.error('Unhandled Rejection:', err);
});
