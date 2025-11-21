const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const Redis = require('ioredis');
const cors = require('cors');
const fetch = require('node-fetch');

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

console.log(' Connecting to Redis...');

const redis = new Redis({
    host: process.env.REDIS_HOST || 'redis',
    port: 6379,
    retryStrategy: (times) => {
        const delay = Math.min(times * 50, 2000);
        console.log(` Redis retry ${times}, delay: ${delay}ms`);
        return delay;
    },
    lazyConnect: true
});

const subscriber = redis.duplicate();

const onlineUsers = new Map();

redis.on('error', (err) => {
    console.error(' Redis Client Error:', err.message);
});

redis.on('connect', () => {
    console.log(' Redis Client CONNECTED to localhost:6379');
});

redis.on('ready', () => {
    console.log(' Redis Client READY');
});

subscriber.on('error', (err) => {
    console.error(' Redis Subscriber Error:', err.message);
});

subscriber.on('connect', () => {
    console.log(' Redis Subscriber CONNECTED to localhost:6379');
});

subscriber.on('ready', () => {
    console.log(' Redis Subscriber READY');

    subscriber.subscribe(
        'laravel-database-chat-message',
        'laravel-database-message-status',
        'message-delivered',
        'message-read',
        (err, count) => {
            if (err) {
                console.error(' Failed to subscribe:', err.message);
            } else {
                console.log(` Subscribed to ${count} channel(s)`);
            }
        }
    );
});

subscriber.on('message', (channel, message) => {
    console.log(` [Redis] ${channel}:`, message);

    try {
        const data = JSON.parse(message);

        switch(channel) {
            case 'laravel-database-chat-message':  // Змініть
            case 'chat-message':
                handleChatMessage(data);
                break;
            case 'laravel-database-message-status':  // Змініть
            case 'message-status':
                handleMessageStatus(data);
                break;
            case 'message-delivered':
                handleMessageDelivered(data);
                break;
            case 'message-read':
                handleMessageRead(data);
                break;
        }
    } catch (e) {
        console.error(' Error parsing message:', e.message);
    }
});

function handleChatMessage(data) {
    const { chatId, message } = data;

    console.log(` Broadcasting to chat-${chatId}`, {
        id: message.id,
        user: message.user_name,
        text: message.message.substring(0, 50)
    });

    io.to(`chat-${chatId}`).emit('new-message', message);
}

function handleMessageStatus(data) {
    const { messageId, userId, status } = data;

    console.log(` Status update: Message ${messageId} -> ${status} for user ${userId}`);

    io.to(`user-${userId}`).emit('message-status-update', {
        messageId,
        status
    });
}

function handleMessageDelivered(data) {
    const { messageId, userId, deliveredAt } = data;
    console.log(` Message ${messageId} delivered to user ${userId}`);
}

function handleMessageRead(data) {
    const { messageId, userId, readAt } = data;
    console.log(` Message ${messageId} read by user ${userId}`);
}
io.on('connection', (socket) => {
    console.log(` Socket connected: ${socket.id}`);

    socket.on('authenticate', (userId) => {
        socket.userId = userId;
        socket.join(`user-${userId}`);
        onlineUsers.set(userId, socket.id);

        console.log(` User ${userId} authenticated (socket: ${socket.id})`);
        socket.broadcast.emit('user-online', { userId });
    });

    socket.on('join-chat', (chatId) => {
        socket.join(`chat-${chatId}`);
        console.log(` User ${socket.userId} joined chat-${chatId}`);
    });

    socket.on('leave-chat', (chatId) => {
        socket.leave(`chat-${chatId}`);
        console.log(` User ${socket.userId} left chat-${chatId}`);
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
        console.log(` Client reported message ${messageId} as delivered`);
        redis.publish('message-delivered', JSON.stringify({
            messageId,
            userId: socket.userId,
            deliveredAt: new Date().toISOString()
        }));
    });

    socket.on('message-read', ({ messageId }) => {
        console.log(`Client reported message ${messageId} as read`);
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
            console.log(` User ${socket.userId} disconnected`);
        }
    });
});

app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        onlineUsers: onlineUsers.size,
        redisConnected: redis.status === 'ready',
        subscriberConnected: subscriber.status === 'ready',
        timestamp: new Date().toISOString()
    });
});

const PORT = process.env.PORT || 3001;

server.listen(PORT, async () => {
    console.log(` Socket.IO server running on port ${PORT}`);

    try {
        await redis.connect();
        await subscriber.connect();
        console.log(' Waiting for Redis messages...');
    } catch (err) {
        console.error(' Failed to connect to Redis:', err.message);
        process.exit(1);
    }
});

process.on('uncaughtException', (err) => {
    console.error(' Uncaught Exception:', err);
});

process.on('unhandledRejection', (err) => {
    console.error(' Unhandled Rejection:', err);
});
