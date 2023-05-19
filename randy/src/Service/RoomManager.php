<?php

namespace App\Service;

use App\Api\Data\ConnectedClientInterface;
use App\Api\Data\RoomInterface;

class RoomManager {

    /**
     * @var RoomInterface[]
     */
    private $rooms;

    public function __construct(array $rooms)
    {
        $this->rooms = $rooms;
    }

    /**
     * Add room
     */
    public function addRoom(RoomInterface $room): RoomManager
    {
        $this->rooms[$room->getId()] = $room;
        return $this;
    }

    /**
     * Get all room names - currently name == id
     */
    public function getRoomNames(): array
    {
        return array_keys($this->rooms);
    }

    /**
     * get room by id
     */
    public function getRoomById(string $roomId)
    {
        if (isset($this->rooms[$roomId])) {
            return $this->rooms[$roomId];
        }

        return null;
    }
    
    /**
     * Find room by client
     */
    public function findRoomByClient(ConnectedClientInterface $client): ?RoomInterface
    {
        foreach ($this->rooms as $room) {
            $clients = $room->getClients();
            if (isset($clients[$client->getResourceId()])) {
                return $room;
            }
        }

        return null;
    }

    /**
     * remove user from room
     */
    public function removeUserFromRoom(ConnectedClientInterface $client, $roomId)
    {
        $room = $this->getRoomById($roomId);

        if (!$room) {
            return false;
        }

        $room->removeClient($client);
        return true;
    }
}