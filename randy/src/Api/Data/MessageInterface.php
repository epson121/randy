<?php

namespace App\Api\Data;

interface MessageInterface {

    public function setData(array $data): MessageInterface;

    public function getData(): array;
    
    public function getJsonData(): string; 
}