<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HeatmapController extends AbstractController
{
    #[Route('/heatmap/{identifier}', name: 'app_heatmap')]
    public function index(string $identifier): Response
    {
        return $this->render('heatmap.html.twig', [
            'identifier' => $identifier,
        ]);
    }
}
