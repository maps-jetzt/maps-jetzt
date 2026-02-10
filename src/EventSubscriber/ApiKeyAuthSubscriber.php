<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiKeyAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        if (str_starts_with($path, '/api/doc')) {
            return;
        }

        $apiKey = $event->getRequest()->headers->get('X-Api-Key');

        if (!$apiKey) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Missing API key'],
                Response::HTTP_UNAUTHORIZED,
            ));
            return;
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['apiKey' => $apiKey]);

        if (!$user) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Invalid API key'],
                Response::HTTP_UNAUTHORIZED,
            ));
            return;
        }

        $event->getRequest()->attributes->set('_user', $user);
    }
}
