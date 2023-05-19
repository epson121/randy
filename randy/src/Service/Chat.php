<?php
namespace App\Service;

use App\Entity\Room;
use Psr\Log\LoggerInterface;
use App\Entity\ConnectedClient;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use App\Api\Data\ConnectedClientInterface;
use App\Api\Data\MessageInterface;
use App\Entity\Message;

class Chat implements MessageComponentInterface {
    
    const ACTION_USERNAME_AUTH = 'user_auth';
    const ACTION_USER_CONNECTED = 'connect';
    const ACTION_USER_JOINED_ROOM = 'joined';
    const ACTION_MESSAGE_RECEIVED = 'message';

    private $roomManager;
    private $clientManager;
    private $messageSender;

    /**
     * 
     */
    public function __construct(
        private LoggerInterface $logger
    ) {
        $this->roomManager = new RoomManager();
        $this->clientManager = new ClientManager();
        $this->messageSender = new MessageSender();
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
    public function onMessage(ConnectionInterface $from, $msg) {
        
        $this->logger->info("New message received: $msg");
        $msg = json_decode($msg, true);
        
        try {
            switch ($msg['action']) {
                case self::ACTION_USERNAME_AUTH:
                    $client = $this->createClient($from, $msg['username']);
                    $this->sendRoomList($client);
                    break;
                case self::ACTION_USER_CONNECTED:
                    $roomId = $this->makeRoom($msg['roomId']);
                    $client = $this->clientManager->getClientByResourceId($from->resourceId);
                    
                    if (!$client) {
                        $this->logger->info("Client does not exist");
                        $from->close();
                        break;
                    }

                    $this->logger->info("{$client->getName()} connected to room $roomId");
                    $this->connectUserToRoom($client, $roomId);
                    $this->sendUserConnectedMessage($client, $roomId);
                    $this->sendRoomUpdates();
                    $this->sendUserUpdates($roomId);
                    
                    if (isset($msg['oldRoomId'])) {
                        $this->roomManager->removeUserFromRoom($client, $msg['oldRoomId']);
                        $this->sendUserUpdates($msg['oldRoomId'], true);
                        // todo send user left message?
                    }
                    break;
                case self::ACTION_MESSAGE_RECEIVED:
                    $client = $this->clientManager->getClientByResourceId($from->resourceId);
                    $roomId = $this->findClientRoom($client);
                    $msg['timestamp'] = isset($msg['timestamp']) ? $msg['timestamp'] : time();
                    $this->sendMessage($client, $roomId, $msg['message'], $msg['timestamp']);
                    break;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());
        }
        
    }

    /**
     * Handle closing of connection
     */
    public function onClose(ConnectionInterface $conn) {
        $this->logger->info("Connection {$conn->resourceId} has disonnected");  
        $this->clientManager->removeClientByResourceId($conn->resourceId);
    }

    /**
     * Send room list to all users
     */
    private function sendRoomUpdates() {
        $roomNames = $this->roomManager->getRoomNames();

        $dataPacket = [
            'rooms' => $roomNames
        ];
        $message = new Message($dataPacket);

        $clients = $this->clientManager->getClients();
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
    protected function findClientRoom(ConnectedClientInterface $client)
    {
        $room = $this->roomManager->findRoomByClient($client);

        if (!$room) {
            throw new \Exception($client->getResourceId());
        }

        return $room->getId();
    }

    protected function createClient(ConnectionInterface $conn, $name)
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

    private function sendUserExistsMessage(ConnectedClientInterface $client, $newUsername) {
        $usersInRoom = [
            'type' => 'username_exists',
            'timestamp' => time(),
            'username' => $newUsername
        ];

        $message = new Message($usersInRoom);
        $this->messageSender->send($message, $client);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function connectUserToRoom(ConnectedClientInterface $client, $roomId)
    {
        $room = $this->roomManager->getRoomById($roomId);
        $room->addClient($client);
    }

    /**
     * @param $roomId
     * @return mixed
     */
    protected function makeRoom($roomId)
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

    private function sendRoomList(ConnectedClientInterface $client) {
        $roomNames = $this->roomManager->getRoomNames();
        $message = new Message([
            'rooms' => $roomNames
        ]);
        $this->messageSender->send($message, $client);
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function sendUserConnectedMessage(ConnectedClientInterface $client, $roomId)
    {
        $name = $client->getName();
        $dataPacket = array(
            'type'=> 'connected',
            'timestamp'=>time(),
            'message'=> "Welcome $name",
            'name' => $name
        );

        $message = new Message($dataPacket);

        $clients = $this->findRoomClients($roomId);
        unset($clients[$client->getResourceId()]);
        $this->messageSender->sendToMany($message, $clients);

        // send list of users in a room, when connected
        $usernames = [];
        foreach ($clients as $c) {
            $usernames[] = $c->getName();
        }

        $usersInRoom = [
            'type' => 'users',
            'timestamp' => time(),
            'users' => $usernames
        ];

        $message = new Message($usersInRoom);
        $this->messageSender->send($message, $client);
        $this->logger->info(json_encode($usersInRoom));
    }

    /**
     * @param $roomId
     * @return array|ConnectedClientInterface[]
     */
    protected function findRoomClients($roomId)
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
    protected function sendMessage(ConnectedClientInterface $client, $roomId, $message, $timestamp)
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