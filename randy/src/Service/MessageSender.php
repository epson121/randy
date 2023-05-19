<?php

namespace App\Service;

use App\Api\Data\MessageInterface;
use App\Api\MessageSenderInterface;
use App\Api\Data\ConnectedClientInterface;

class MessageSender implements MessageSenderInterface {

    public function sendToMany(MessageInterface $message, array $clients) {
        foreach ($clients as $client) {
            $this->send($message, $client);
        }
    }

    public function send(MessageInterface $message, ConnectedClientInterface $client) {
        $client->getConnection()->send($message->getJsonData());
    }

}