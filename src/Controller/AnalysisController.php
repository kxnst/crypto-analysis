<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnalysisController extends AbstractController
{
    #[Route(path:'/analysis/', methods: 'GET')]
    public function index(): Response
    {
        return $this->render('main.html.twig');
    }
}
