<?php

namespace App\Service;

use App\Api\Data\ConnectedClientInterface;

class ClientManager {

    private array $clients;

    public function __construct(array $clients = [])
    {
        $this->clients = $clients;
    }

    public function getClients()
    {
        return $this->clients;
    }

    public function getClientByUsername($username)
    {
        foreach($this->clients as $client) {
            if ($client->getName() == $username) {
                return $client;
            }
        }

        return false;
    }

    public function getClientByResourceId($resourceId)
    {
        if (isset($this->clients[$resourceId])) {
            return $this->clients[$resourceId];
        }

        throw new \Exception($resourceId);
        
    }

    public function addClient(ConnectedClientInterface $client) {
        $this->clients[$client->getResourceId()] = $client;
    }

    public function removeClientByResourceId($resourceId)
    {
        foreach($this->clients as $clientResourceId => $client) {
            if ($clientResourceId == $resourceId) {
                unset($this->clients[$clientResourceId]);
            }
        }
    }

}