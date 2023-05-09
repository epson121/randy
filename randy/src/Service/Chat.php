<?php
namespace App\Service;

use App\Entity\ConnectedClient;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use App\Api\Data\ConnectedClientInterface;
use Psr\Log\LoggerInterface;

class Chat implements MessageComponentInterface {
    
    const ACTION_USERNAME_AUTH = 'user_auth';
    const ACTION_USER_CONNECTED = 'connect';
    const ACTION_USER_JOINED_ROOM = 'joined';
    const ACTION_MESSAGE_RECEIVED = 'message';

    private $clients;
    private $rooms;    

    /**
     * 
     */
    public function __construct(private LoggerInterface $logger) {
        $this->clients = [];
        $this->rooms = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->logger->info("New Connection! ({$conn->resourceId})");
        // $roomNames = array_keys($this->rooms);
        // $conn->send(json_encode(['rooms' => $roomNames]));
    }

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
                    $client = $this->getClientByResourceId($from->resourceId);
                    
                    if (!$client) {
                        // do something
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
                        unset($this->rooms[$msg['oldRoomId']][$client->getResourceId()]);
                        $this->sendUserUpdates($msg['oldRoomId'], true);
                        // send user left message?
                    }
                    break;
                case self::ACTION_MESSAGE_RECEIVED:
                    $client = $this->findClient($from);
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

    public function onClose(ConnectionInterface $conn) {
        $this->logger->info("Connection {$conn->resourceId} has disonnected");
        
        foreach($this->clients as $k => $client) {
            if ($client->getResourceId() == $conn->resourceId) {
                unset($this->clients[$k]);
            }
        }
    }

    private function sendRoomUpdates() {
        $roomNames = array_keys($this->rooms);

        $dataPacket = [
            'rooms' => $roomNames
        ];

        $this->sendDataToClients($this->clients, $dataPacket);
    }

    private function sendUserUpdates($roomId, $clean = false) {
        $clients = $this->rooms[$roomId];
        $clientList = [];
        foreach ($clients as $k => $client) {
            $clientList[] = $client->getName();
        }

        $dataPacket = [
            'type' => 'userlist',
            'users' => $clientList
        ];

        $this->sendDataToClients($clients, $dataPacket);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logger->error("An error has occurred: {$e->getMessage()}");
        $conn->close();
    }

    /**
     * @param ConnectionInterface $conn
     * @return ConnectedClientInterface
     */
    protected function findClient(ConnectionInterface $conn)
    {
        if (isset($this->clients[$conn->resourceId])) {
            return $this->clients[$conn->resourceId];
        }

        throw new \Exception($conn->resourceId);
    }

    protected function getClientByUsername($username) {
        foreach($this->clients as $client) {
            if ($client->getName() == $username) {
                return $client;
            }
        }

        return false;
    }

    private function getClientByResourceId($resourceId) {
        foreach($this->clients as $client) {
            if ($client->getResourceId() == $resourceId) {
                return $client;
            }
        }

        return false;
    }

    /**
     * @param ConnectedClientInterface $client
     * @return int|string
     */
    protected function findClientRoom(ConnectedClientInterface $client)
    {
        foreach ($this->rooms AS $roomId=>$roomClients) {
            if (isset($roomClients[$client->getResourceId()])) {
                return $roomId;
            }
        }

        throw new \Exception($client->getResourceId());
    }

    protected function createClient(ConnectionInterface $conn, $name)
    {
        $client = $this->getClientByUsername($name);

        if ($client) {
            // send user already exists
            $bytes = random_bytes(4);
            $name = $name . '_' . bin2hex($bytes); 
            $this->sendUserExistsMessage($conn, $name);
        }

        $client = new ConnectedClient();
        $client->setResourceId($conn->resourceId);
        $client->setConnection($conn);
        $client->setName($name);

        $this->clients[$client->getResourceId()] = $client;

        return $client;
    }

    private function sendUserExistsMessage(ConnectionInterface $conn, $newUsername) {
        $usersInRoom = [
            'type' => 'username_exists',
            'timestamp' => time(),
            'username' => $newUsername
        ];

        return $conn->send(json_encode($usersInRoom));
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function connectUserToRoom(ConnectedClientInterface $client, $roomId)
    {
        $this->rooms[$roomId][$client->getResourceId()] = $client;
    }


    /**
     * @param $roomId
     * @return mixed
     */
    protected function makeRoom($roomId)
    {
        $this->logger->info("Creating a room {$roomId}");
        if (!isset($this->rooms[$roomId])) {
            $this->logger->info('Room does not exist');
            $this->rooms[$roomId] = [];
        }
        
        return $roomId;
    }

    private function sendRoomList(ConnectedClientInterface $client) {
        $roomNames = array_keys($this->rooms);
        $client->getConnection()->send(json_encode(['rooms' => $roomNames]));
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

        $clients = $this->findRoomClients($roomId);
        unset($clients[$client->getResourceId()]);
        $this->sendDataToClients($clients, $dataPacket);

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

        $this->logger->info(json_encode($usersInRoom));

        $client->getConnection()->send(json_encode($usersInRoom));
    }

    /**
     * @param $roomId
     * @return array|ConnectedClientInterface[]
     */
    protected function findRoomClients($roomId)
    {
        return $this->rooms[$roomId];
    }

        /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     * @param $message
     * @param $timestamp
     */
    protected function sendMessage(ConnectedClientInterface $client, $roomId, $message, $timestamp)
    {
        $dataPacket = array(
            'type'=> 'message',
            'from'=>$client->asArray(),
            'timestamp'=>$timestamp,
            'message' => $message
        );

        $clients = $this->findRoomClients($roomId);
        $this->sendDataToClients($clients, $dataPacket);
    }

        /**
     * @param array|ConnectedClientInterface[] $clients
     * @param array $packet
     */
    protected function sendDataToClients(array $clients, array $packet)
    {
        foreach ($clients as $client) {
            $this->sendData($client, $packet);
        }
    }

        /**
     * @param ConnectedClientInterface $client
     * @param array $packet
     */
    protected function sendData(ConnectedClientInterface $client, array $packet)
    {
        $client->getConnection()->send(json_encode($packet));
    }
}