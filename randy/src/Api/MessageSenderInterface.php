<?php

namespace App\Api;

use App\Api\Data\MessageInterface;
use App\Api\Data\ConnectedClientInterface;

interface MessageSenderInterface {

    public function send(MessageInterface $message, ConnectedClientInterface $client);

    public function sendToMany(MessageInterface $message, array $clients);

}