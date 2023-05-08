<template>
    <div>
        <div>{{ title }}</div>

        <div id='content'></div>
        
        <form id="messageForm" v-on:submit.prevent="sendChatMessage">
            <p>
                <textarea  v-model="message" name="message" placeholder="Enter your message..." >{{ message }}</textarea>
            </p>
            <p>
                <input type="submit" value="Send Message"  />
            </p>
        </form>
    </div>
</template>

<script>
    export default {
        name: "app",
        data() {
            return {
                title: "Welcome to the chat",
                message: ""
            }
        },
        mounted() {
            let self = this;
            window.conn = new WebSocket('wss://app.randy.test:8080');
            conn.onopen = function(e) {
                console.log("Connection established!");

                var d = new Date();
                var params = {
                    'roomId': 'general',
                    'userName': d.getTime()/1000 + "_user",
                    'action': 'connect'
                };
                console.log(params);
                conn.send(JSON.stringify(params));
            };

            conn.onmessage = function(e) {
                console.log(e);
                var data = JSON.parse(e.data);

                if (data.hasOwnProperty('message') && data.hasOwnProperty('from')) {
                    self.displayChatMessage(data.from.name, data.message);
                }
                else if (data.hasOwnProperty('message')) {
                    self.displayChatMessage(null, data.message);
                }
            };
        },
        methods: {
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
                conn.send(JSON.stringify(params));

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