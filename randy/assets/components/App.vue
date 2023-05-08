<template>
    <div>
        <div>{{ title }}</div>

        <div v-if="joinedRoom === false">
            <label for="username">Username</label>
            <input type="text" name="username" v-model="username">
            <label for="roomId">Room</label>
            <input type="text" name="roomId" v-model="roomId">
            <button @click="joinRoom">Join</button>
        </div>

        <div v-if="joinedRoom === true">
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
        <div>
            <p>Available rooms:</p>
            <ul id="rooms">
                <li v-for="room in rooms">
                {{ room }}
                </li>
            </ul>
        </div>

        <div v-if="joinedRoom === true">
            <p>Users in room:</p>
            <ul id="rooms">
                <li v-for="user in usersInRoom">
                {{ user }}
                </li>
            </ul>
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
                conn: null,
                username: null,
                roomId: null,
                rooms: [],
                usersInRoom: [],
                joinedRoom: false
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
            },
            joinRoom: function() {
                if (!this.username || !this.roomId) {
                    console.error('Username or roomId are missing');
                    return;
                }

                var d = new Date();
                var params = {
                    'roomId': this.roomId,
                    'userName': this.username,
                    'action': 'connect'
                };
                console.log(params);
                this.conn.send(JSON.stringify(params));
                this.joinedRoom = true;
                this.usersInRoom.push(this.username);
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