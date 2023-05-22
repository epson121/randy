<?php
namespace App\Service;

use App\Entity\Room;
use App\Entity\Message;
use Psr\Log\LoggerInterface;
use App\Entity\ConnectedClient;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use App\Entity\Message\RoomListMessage;
use App\Entity\Message\UserLeftMessage;
use App\Api\Data\ConnectedClientInterface;
use App\Entity\Message\UserConnectedMessage;
use App\Entity\Message\UsernameExistsMessage;

class Chat implements MessageComponentInterface {
    
    const ACTION_USERNAME_AUTH = 'user_auth';
    const ACTION_USER_CONNECTED = 'connect';
    const ACTION_MESSAGE_RECEIVED = 'message';

    public function __construct(
        private LoggerInterface $logger,
        private ClientManager $clientManager,
        private MessageSender $messageSender,
        private RoomManager $roomManager
    ) {
    }

    /**
     * Called when new connection is established
     */
    public function onOpen(ConnectionInterface $conn) {
        $this->logger->info("New Connection! ({$conn->resourceId})");
    }

    /**
     * Handle various message types, and perform appropriate actions
     */
    public function onMessage(ConnectionInterface $connection, $msg) {
        
        $this->logger->info("New message received: $msg");
        $msg = json_decode($msg, true);
        
        try {
            switch ($msg['action']) {
                case self::ACTION_USERNAME_AUTH:
                    $client = $this->createClient($connection, $msg['username']);
                    $this->sendRoomList($client);
                    break;
                case self::ACTION_USER_CONNECTED:
                    $roomId = $this->makeRoom($msg['roomId']);
                    $client = $this->clientManager->getClientByResourceId($connection->resourceId);
                    
                    if (!$client) {
                        $this->logger->info("Client does not exist");
                        $connection->close();
                        break;
                    }

                    $this->logger->info("{$client->getName()} connected to room $roomId");
                    $this->connectUserToRoom($client, $roomId);
                    
                    if (isset($msg['oldRoomId'])) {
                        $this->removeUserFromRoom($client, $msg['oldRoomId']);
                    }
                    break;
                case self::ACTION_MESSAGE_RECEIVED:
                    $this->processMessage($connection, $msg);
                    break;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());
        }
    }

    /**
     * Process received message
     */
    private function processMessage(ConnectionInterface $connection, array $message) {
        $client = $this->clientManager->getClientByResourceId($connection->resourceId);
        $roomId = $this->findClientRoom($client);

        $msg['timestamp'] = isset($message['timestamp']) ? $message['timestamp'] : time();
        
        $this->sendMessage($client, $roomId, $message['message'], $message['timestamp']);
    }

    /**
     * Handle closing of connection
     */
    public function onClose(ConnectionInterface $conn) {
        $this->logger->info("Connection {$conn->resourceId} has disonnected");  
        $this->clientManager->removeClientByResourceId($conn->resourceId);
    }

    /**
     * Send list of rooms available
     */
    private function sendRoomList(ConnectedClientInterface $client = null) {
        $roomNames = $this->roomManager->getRoomNames();
        $message = new RoomListMessage([
            'rooms' => $roomNames
        ]);

        if ($client) {
            $this->messageSender->send($message, $client);
        } else {
            $clients = $this->clientManager->getClients();
            $this->messageSender->sendToMany($message, $clients);
        }
    }

    /**
     * Send updated user list to all users in a room
     */
    private function sendUserLeft($name, $roomId) {
        $room = $this->roomManager->getRoomById($roomId);
        
        if (!$room) {
            throw new \Exception('Cant send updates. No room id exists');
        }
        
        $clients = $room->getClients();

        $dataPacket = [
            'type' => 'user_left',
            'message' => "$name left"
        ];

        $message = new UserLeftMessage($dataPacket);

        $this->messageSender->sendToMany($message, $clients);
    }


    /**
     * Send updated user list to all users in a room
     */
    private function sendUserUpdates($roomId) {
        $room = $this->roomManager->getRoomById($roomId);
        
        if (!$room) {
            throw new \Exception('Cant send updates. No room id exists');
        }
        
        $clients = $room->getClients();
        
        $clientList = [];
        foreach ($clients as $k => $client) {
            $clientList[] = $client->getName();
        }

        $dataPacket = [
            'type' => 'userlist',
            'users' => $clientList
        ];

        $message = new Message($dataPacket);

        $this->messageSender->sendToMany($message, $clients);
    }

    /**
     * Handle error with connection
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logger->error("An error has occurred: {$e->getMessage()}");
        $conn->close();
    }

    /**
     * @param ConnectedClientInterface $client
     * @return int|string
     */
    private function findClientRoom(ConnectedClientInterface $client)
    {
        $room = $this->roomManager->findRoomByClient($client);

        if (!$room) {
            throw new \Exception($client->getResourceId());
        }

        return $room->getId();
    }

    /**
     * Create a new client object
     */
    private function createClient(ConnectionInterface $conn, $name)
    {
        $exists = false;
        $client = $this->clientManager->getClientByUsername($name);

        if ($client) {
            $exists = true;
            $bytes = random_bytes(4);
            $name = $name . '_' . bin2hex($bytes); 
        }

        $client = new ConnectedClient();
        $client->setResourceId($conn->resourceId);
        $client->setConnection($conn);
        $client->setName($name);

        if ($exists) {
            $this->sendUserExistsMessage($client, $name);
        }

        $this->clientManager->addClient($client);

        return $client;
    }

    /**
     * Send UsernameExistsMessage
     */
    private function sendUserExistsMessage(ConnectedClientInterface $client, $newUsername) {
        $usernameExists = [
            'type' => 'username_exists',
            'timestamp' => time(),
            'username' => $newUsername
        ];

        $message = new UsernameExistsMessage($usernameExists);
        $this->messageSender->send($message, $client);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    private function connectUserToRoom(ConnectedClientInterface $client, $roomId)
    {
        $room = $this->roomManager->getRoomById($roomId);
        if (!$room) {
            throw new \Exception("Can't connect to room $roomId. Room does not exist.");
        }

        $room->addClient($client);

        $this->sendUserConnectedMessage($client, $roomId);
        $this->sendRoomList();
        $this->sendUserUpdates($roomId);
    }

    /**
     * @var ConnectedClient $client
     * @var string $roomId
     * Remove user from a room, and notify
     */
    private function removeUserFromRoom($client, $roomId) {
        if ($this->roomManager->removeUserFromRoom($client, $roomId)) {
            $this->sendUserUpdates($roomId);
            $this->sendUserLeft($client->getName(), $roomId);
        }
    }

    /**
     * @param $roomId
     * @return mixed
     */
    private function makeRoom($roomId)
    {
        $this->logger->info("Creating a room {$roomId}");

        $room = $this->roomManager->getRoomById($roomId);
        if (!$room) {
            $this->logger->info('Room does not exist');
            $room = new Room();
            $room->setId($roomId);
            $room->setName($roomId);
            $this->roomManager->addRoom($room);
        }
        
        return $room->getId();
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    private function sendUserConnectedMessage(ConnectedClientInterface $client, $roomId)
    {
        $name = $client->getName();
        $dataPacket = [
            'type'=> 'connected',
            'timestamp'=>time(),
            'message'=> "Welcome $name",
            'name' => $name
        ];

        $message = new UserConnectedMessage($dataPacket);

        $clients = $this->findRoomClients($roomId);
        unset($clients[$client->getResourceId()]);
        $this->messageSender->sendToMany($message, $clients);
    }

    /**
     * @param $roomId
     * @return array|ConnectedClientInterface[]
     */
    private function findRoomClients($roomId)
    {
        $room = $this->roomManager->getRoomById($roomId);
        if ($room) {
            return $room->getClients();
        }
        return null;
    }

    /**
     * @param ConnectedClientInterface $client
     * @param string $roomId
     * @param string $message
     * @param string $timestamp
     */
    private function sendMessage(ConnectedClientInterface $client, $roomId, $message, $timestamp)
    {
        $message = new Message([
            'type' => 'message',
            'from' => $client->asArray(),
            'timestamp' => $timestamp,
            'message' => $message
        ]);

        $clients = $this->findRoomClients($roomId);
        $this->messageSender->sendToMany($message, $clients);
    }
}