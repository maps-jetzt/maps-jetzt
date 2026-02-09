<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Heatmap;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HeatmapController extends AbstractController
{
    #[Route('/heatmap/{identifier}', name: 'app_heatmap')]
    public function index(string $identifier, EntityManagerInterface $em): Response
    {
        $heatmap = $em->getRepository(Heatmap::class)->findOneBy(['identifier' => $identifier]);

        return $this->render('heatmap.html.twig', [
            'identifier' => $identifier,
            'centerLon' => $heatmap?->getCenterLon() ?? 10,
            'centerLat' => $heatmap?->getCenterLat() ?? 53,
            'zoom' => $heatmap?->getZoom() ?? 12,
        ]);
    }
}
