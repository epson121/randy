<?php
namespace App\Api\Data;

interface RoomInterface
{

    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return RoomInterface
     */
    public function setId(string $id): RoomInterface;
    
    public function getName(): string;

    /**
     * @return RoomInterface
     */
    public function setName(string $name): RoomInterface;

    /**
     * @return ConnectedClientinterface[]
     */
    public function getClients(): array;

    /**
     * @return RoomInterface
     */
    public function addClient(ConnectedClientInterface $client): RoomInterface;
    
    /**
     * @return RoomInterface
     */
    public function removeClient(ConnectedClientInterface $client): RoomInterface;
    
    /**
     * @return array
     */
    public function asArray(): array;

}