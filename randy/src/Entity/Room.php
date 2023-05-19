<?php

namespace App\Entity;

use App\Api\Data\RoomInterface;
use App\Api\Data\ConnectedClientInterface;

class Room implements RoomInterface {

    /**
     * Room identification
     */
    private string $id;

    /**
     * Room name
     */
    private string $name;

    /**
     * List of room clients
     * @var ConnectedClientinterface[]
     */
    private array $clients;

    /**
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * @return RoomInterface
     */
    public function setId(string $id): RoomInterface {
        $this->id = $id;

        return $this;
    }
    
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return RoomInterface
     */
    public function setName(string $name): RoomInterface {
        $this->name = $name;

        return $this;
    }

    /**
     * @return ConnectedClientinterface[]
     */
    public function getClients(): array {
        return $this->clients;
    }

    /**
     * @return RoomInterface
     */
    public function addClient(ConnectedClientInterface $client): RoomInterface {
        $this->clients[$client->getResourceId()] = $client;

        return $this;
    }
    
    /**
     * @return RoomInterface
     */
    public function removeClient(ConnectedClientInterface $client): RoomInterface {
        unset($this->clients[$client->getResourceId()]);

        return $this;
    }
    
    /**
     * @return array
     */
    public function asArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'clients' => count($this->clients)
        ];
    }

}