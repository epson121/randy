<?php
namespace App\Service;

use App\Entity\ConnectedClient;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use App\Api\Data\ConnectedClientInterface;

class Chat implements MessageComponentInterface {
    
    const ACTION_USER_CONNECTED = 'connect';
    const ACTION_MESSAGE_RECEIVED = 'message';

    protected $clients;

    private $rooms;

    public function __construct() {
        $this->clients = [];
        $this->rooms = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        
        echo "New message received: $msg";
        $msg = json_decode($msg, true);
        
        try {
            switch ($msg['action']) {
                case self::ACTION_USER_CONNECTED:
                    $roomId = $this->makeRoom($msg['roomId']);
                    $client = $this->createClient($from, $msg['userName']);
                    echo "User connected to $roomId";
                    $this->connectUserToRoom($client, $roomId);
                    $this->sendUserConnectedMessage($client, $roomId);
                    break;
                case self::ACTION_MESSAGE_RECEIVED:
                    $client = $this->findClient($from);
                    $roomId = $this->findClientRoom($client);
                    $msg['timestamp'] = isset($msg['timestamp']) ? $msg['timestamp'] : time();
                    $this->sendMessage($client, $roomId, $msg['message'], $msg['timestamp']);
                    break;
            }
        } catch (\Exception $e) {
            echo "Exception: $e";
        }
        
    }

    public function onClose(ConnectionInterface $conn) {
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
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
        $client = new ConnectedClient();
        $client->setResourceId($conn->resourceId);
        $client->setConnection($conn);
        $client->setName($name);

        return $client;
    }

    /**
     * @param ConnectedClientInterface $client
     * @param $roomId
     */
    protected function connectUserToRoom(ConnectedClientInterface $client, $roomId)
    {
        $this->rooms[$roomId][$client->getResourceId()] = $client;
        $this->clients[$client->getResourceId()] = $client;
    }


    /**
     * @param $roomId
     * @return mixed
     */
    protected function makeRoom($roomId)
    {
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [];
        }

        return $roomId;
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
            'message'=> "Welcome $name"
        );

        $clients = $this->findRoomClients($roomId);
        unset($clients[$client->getResourceId()]);
        $this->sendDataToClients($clients, $dataPacket);
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