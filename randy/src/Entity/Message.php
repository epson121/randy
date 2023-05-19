<?php

namespace App\Entity;

use App\Api\Data\MessageInterface;

class Message implements MessageInterface {

    public function __construct(
        private array $data
    ) {}

    public function setData(array $data = []): MessageInterface {
        $this->data = $data;
        return $this;
    }

    public function getData(): array {
        return $this->data;
    }
    
    public function getJsonData(): string {
        return json_encode($this->data);
    }

}