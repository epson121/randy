<?php

namespace App\Command;

use App\Chat;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:server:start',
    description: 'command',
)]
class StartServer extends Command
{

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $app = new \Ratchet\Http\HttpServer(
            new \Ratchet\WebSocket\WsServer(
                new Chat()
            )
        );
        
        $loop = \React\EventLoop\Factory::create();
        
        $secure_websockets = new \React\Socket\Server('0.0.0.0:8080', $loop);
        $secure_websockets = new \React\Socket\SecureServer($secure_websockets, $loop, [
            'local_cert' => '/var/www/html/cert.pem',
            'local_pk' => '/var/www/html/key.pem',
            'verify_peer' => false
        ]);
        
        $secure_websockets_server = new \Ratchet\Server\IoServer($app, $secure_websockets, $loop);
        $secure_websockets_server->run();

    }
}
