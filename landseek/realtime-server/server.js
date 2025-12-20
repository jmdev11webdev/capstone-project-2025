const express = require('express'); // Express framework
const http = require('http'); // HTTP server
const { Server } = require('socket.io'); // Socket.IO
const cors = require('cors'); // CORS middleware

const app = express(); // Create Express app
app.use(cors()); // Enable CORS for all origins

const server = http.createServer(app); // Create HTTP server
const io = new Server(server, {
  cors: { origin: "*" } // allow your PHP frontend origin
});

// Store connected users
let connectedUsers = {};

// Handle socket connections
io.on('connection', (socket) => {
    console.log('New client connected:', socket.id);

    // Register user on connection
    socket.on('register', (userId) => {
        connectedUsers[userId] = socket.id;
        console.log('User registered:', userId);
    });

    // Handle sending message
    socket.on('send_message', (data) => {
        const { receiver_id, message } = data;

        // Emit message to receiver if connected
        if (connectedUsers[receiver_id]) {
            io.to(connectedUsers[receiver_id]).emit('receive_message', data);
        }
    });

    // Handle notifications
    socket.on('send_notification', (data) => {
        const { user_id, notif } = data;
        if (connectedUsers[user_id]) {
            io.to(connectedUsers[user_id]).emit('receive_notification', notif);
        }
    });

    // Handle disconnection
    socket.on('disconnect', () => {
        console.log('Client disconnected:', socket.id);
        // Remove from connected users
        for (let uid in connectedUsers) {
            if (connectedUsers[uid] === socket.id) delete connectedUsers[uid];
        }
    });
});

// Start server
const PORT = 3001; // Port for WebSocket server
server.listen(PORT, () => console.log(`WebSocket server running on port ${PORT}`)); // Start server
