<?php

namespace App\Command;

use App\Service\Chat;
use Psr\Log\LoggerInterface;
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

    public function __construct(
        private LoggerInterface $chatServerLogger,
        private Chat $chat,
        private string $sslKeyPath,
        private string $sslCertPath,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->chatServerLogger->info('Starting server.');
        $app = new \Ratchet\Http\HttpServer(
            new \Ratchet\WebSocket\WsServer(
                $this->chat
            )
        );
        
        $loop = \React\EventLoop\Factory::create();
        
        $secureWebsocket = new \React\Socket\Server('0.0.0.0:8080', $loop);
        $secureWebsocket = new \React\Socket\SecureServer($secureWebsocket, $loop, [
            'local_cert' => $this->sslCertPath,
            'local_pk' => $this->sslKeyPath,
            'verify_peer' => false
        ]);
        
        $secureWebsocketServer = new \Ratchet\Server\IoServer($app, $secureWebsocket, $loop);
        $this->chatServerLogger->info('Server created.');
        $this->chatServerLogger->info('Running server.');
        $secureWebsocketServer->run();
    }
}
