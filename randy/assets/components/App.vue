<template>
    <div>

        <div v-if="!userAuthenticated" class="login-block">
            <div class="username label">
                <label for="username">Username</label>
            </div>
            <div class="username input">
                <input type="text" name="username" v-model="username" class="input" placeholder="Username">
                <button @click="connectUser" class="button">Connect</button>
            </div>
        </div>

        <div class="rooms-block" v-if="userAuthenticated">
            <p class="rooms label">Rooms:</p>
            <ul id="rooms" v-if="rooms.length" class="rooms list">
                <li v-for="room in rooms" class="rooms item">
                {{ room }}
                </li>
            </ul>
            <p class="rooms no label" v-else>No rooms available at the moment.</p>
        </div>

        <div v-if="userAuthenticated" class="join-room">
            <label for="roomId">Create a new room, or join an existing one:</label>
            <div class="rooms input">
                <input type="text" name="roomId" v-model="roomId" class="input">
                <button @click="joinRoom" class="button">Join</button>
            </div>
        </div>

        <div v-if="joinedRoom" class="chatroom">
            <div class="label">Chat <span class="login-info"> (you are logged in as {{username}})</span></div>
            <div id='content' class="content" ref="content"></div>
            
            
            <form id="messageForm" v-on:submit.prevent="sendChatMessage" class="form">
                <div>
                    <input class="input" v-model="message" name="message" placeholder="Enter your message..." />
                    <input class="button" type="submit" value="Send Message"  />
                </div>
            </form>
        </div>

        <div v-if="joinedRoom" class="room-users">
            <p class="label">Users in room:</p>
            <ul id="rooms" v-if="usersInRoom.length" class="users-list">
                <li v-for="user in usersInRoom" class="user-item">
                {{ user }}
                </li>
            </ul>
            <p class="no label" v-else>Room is empty</p>
        </div>

    </div>
</template>

<script>
    export default {
        name: "app",
        data() {
            return {
                title: "Welcome to the chat",
                message: "",
                wss_base_url: window.config.wss_base_url,
                userAuthenticated: false,
                conn: null,
                username: null,
                roomId: null,
                rooms: [],
                usersInRoom: [],
                joinedRoom: null
            }
        },
        mounted() {
            this.connectToServer();
        },
        methods: {
            connectToServer: function() {
                let self = this;
                if (!this.wss_base_url) {
                    console.error('Wss Base Url not set');
                    return;
                }

                this.conn = new WebSocket(this.wss_base_url);
                this.conn.onopen = function(e) {
                };

                this.conn.onmessage = function(e) {
                    console.log(e.data);
                    var data = JSON.parse(e.data);
                    console.log(data);

                    if (data.hasOwnProperty('rooms')) {
                        self.rooms = data.rooms;
                    }

                    if (data.hasOwnProperty('type') && data.type == 'userlist') {
                        self.usersInRoom = data.users;
                    }

                    if (data.hasOwnProperty('type') && data.type == 'username_exists') {
                        self.username = data.username;
                        alert("Username is already taken, your new username is " + self.username);
                    }

                    if (data.hasOwnProperty('message') && data.hasOwnProperty('from')) {
                        self.displayChatMessage(data.message, data.from.name);
                    }
                    
                    if (data.hasOwnProperty('message') && data.hasOwnProperty('type') && data.type == 'connected') {
                        self.displayChatMessage(data.message);
                        self.usersInRoom.push(data.name);
                    }

                    if (data.hasOwnProperty('users') && data.hasOwnProperty('type') && data.type == 'users') {
                        console.log('Updating users');
                        console.log(self.usersInRoom);
                        self.usersInRoom = self.usersInRoom.concat(data.users);
                    }

                    if (data.hasOwnProperty('type') && data.type == 'user_left' && data.hasOwnProperty('message')) {
                        self.displayChatMessage(data.message);
                    }
                };

                this.conn.onclose = function(e) {
                    self.username = "";
                    self.userAuthenticated = false;
                    self.rooms = [];
                    self.roomId = null;
                    self.usersInRoom = [];
                    self.joinedRoom = null;
                }
            },
            connectUser: function() {
                if (!this.username) {
                    return;
                }

                var d = new Date();
                var params = {
                    'username': this.username,
                    'action': 'user_auth'
                };
                console.log(params);
                this.conn.send(JSON.stringify(params));
                this.userAuthenticated = true;
            },
            joinRoom: function() {
                if (!this.username || !this.roomId) {
                    console.error('Username or roomId are missing');
                    return;
                }

                var d = new Date();
                var params = {
                    'roomId': this.roomId,
                    'action': 'connect',
                    'oldRoomId': this.joinedRoom
                };
                console.log(params);
                this.conn.send(JSON.stringify(params));
                this.joinedRoom = this.roomId;
                var contentEl = this.$refs.content;
                if (contentEl) {
                    contentEl.innerHTML = '';
                }
                this.displayChatMessage('You have joined ' + this.joinedRoom);
            },
            sendChatMessage: function () {
                if (!this.message) {
                    return false;
                }
                var d = new Date();
                var params = {
                    'message': this.message,
                    'action': 'message',
                    'timestamp': d.getTime()/1000,
                };
                console.log(JSON.stringify(params));
                this.conn.send(JSON.stringify(params));

                this.message = '';
            },
            displayChatMessage: function (message, from) {
                var node = document.createElement("div");
                node.classList.add('message');

                if (from) {
                    var nameNode = document.createElement("div");
                    var nameTextNode = document.createTextNode(from + ":");
                    nameNode.appendChild(nameTextNode);
                    nameNode.classList.add('from');
                    node.appendChild(nameNode);
                }
                
                var messageNode = document.createElement("div");
                var messageTextNode = document.createTextNode(message);
                messageNode.append(messageTextNode);
                messageNode.classList.add('msg');

                if (!from) {
                    messageNode.classList.add('no-from');
                }
                node.appendChild(messageNode);

                var contentEl = this.$refs.content;
                if (contentEl) {
                    contentEl.appendChild(node);
                }
            }
        }
    }
</script>

<style scoped>
</style>