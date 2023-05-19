<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AppController extends AbstractController
{

    #[Route('/', name: 'homepage')]
    public function chat(
        string $wssBaseUrl
        ): Response {
        return $this->render('home.html.twig', [
            'wss_base_url' => $wssBaseUrl
        ]);
    }

}