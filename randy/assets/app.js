/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// start the Stimulus application
import './bootstrap';

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
        displayChatMessage(data.from.name, data.message);
    }
    else if (data.hasOwnProperty('message')) {
        displayChatMessage(null, data.message);
    }
};

window.displayChatMessage = function(from, message) {
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


window.sendChatMessage = function sendChatMessage() {
    var d = new Date();
    var params = {
        'message': document.getElementsByName("message")[0].value,
        'action': 'message',
        'timestamp': d.getTime()/1000,
    };
    conn.send(JSON.stringify(params));

    document.getElementsByName("message")[0].value = '';
    return false;
}