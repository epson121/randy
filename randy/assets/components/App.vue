<template>
    <div>
        <div>{{ title }}</div>

        <div v-if="!userAuthenticated">
            <label for="username">Username</label>
            <input type="text" name="username" v-model="username">
            <button @click="connectUser">Connect</button>
        </div>

        <div v-if="userAuthenticated">
            <p >Available rooms:</p>
            <ul id="rooms" v-if="rooms.length">
                <li v-for="room in rooms">
                {{ room }}
                </li>
            </ul>
            <p v-else>No rooms available at the moment.</p>
        </div>

        <div v-if="userAuthenticated">
            <label for="roomId">Create a new room, or join an existing one:</label>
            <input type="text" name="roomId" v-model="roomId">
            <button @click="joinRoom">Join</button>
        </div>

        <div v-if="joinedRoom">
            <div id='content'></div>
            
            
            <form id="messageForm" v-on:submit.prevent="sendChatMessage" >
                <p>
                    <textarea  v-model="message" name="message" placeholder="Enter your message..." >{{ message }}</textarea>
                </p>
                <p>
                    <input type="submit" value="Send Message"  />
                </p>
            </form>
        </div>

        <div v-if="joinedRoom">
            <p>Users in room:</p>
            <ul id="rooms" v-if="usersInRoom.length">
                <li v-for="user in usersInRoom">
                {{ user }}
                </li>
            </ul>
            <p v-else>Room is empty</p>
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
                        self.displayChatMessage(data.from.name, data.message);
                    }
                    
                    if (data.hasOwnProperty('message') && data.hasOwnProperty('type') && data.type == 'connected') {
                        self.displayChatMessage(null, data.message);
                        self.usersInRoom.push(data.name);
                    }

                    if (data.hasOwnProperty('users') && data.hasOwnProperty('type') && data.type == 'users') {
                        console.log('Updating users');
                        console.log(self.usersInRoom);
                        self.usersInRoom = self.usersInRoom.concat(data.users);
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
            displayChatMessage: function (from, message) {
                var node = document.createElement("div");

                if (from) {
                    var nameNode = document.createElement("strong");
                    var nameTextNode = document.createTextNode(from + ":");
                    nameNode.appendChild(nameTextNode);
                    node.appendChild(nameNode);
                }

                var messageTextNode = document.createTextNode(message);
                node.appendChild(messageTextNode);

                document.getElementById("content").appendChild(node);
            }
        }
    }
</script>

<style scoped>
</style>