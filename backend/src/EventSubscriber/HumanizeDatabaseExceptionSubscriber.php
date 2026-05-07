<?php

namespace App\EventSubscriber;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * [LOT-0] Convertit les erreurs SQL brutes en messages métier humanisés
 * pour que le toast frontend ne fuite pas de SQL/contraintes Doctrine.
 *
 * Couvre uniquement les routes /api/* en JSON.
 */
class HumanizeDatabaseExceptionSubscriber
{
    #[AsEventListener(event: 'kernel.exception', priority: 100)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $previous = $exception->getPrevious();

        // Remonte la chaîne pour trouver une exception DBAL connue.
        $chain = [$exception, $previous, $previous?->getPrevious()];

        foreach ($chain as $candidate) {
            if ($candidate instanceof UniqueConstraintViolationException) {
                $event->setResponse(new JsonResponse(
                    [
                        'error' => 'duplicate',
                        'message' => 'Cet enregistrement existe déjà (un champ unique est dupliqué). Vérifie le code, le numéro ou l\'identifiant utilisé.',
                    ],
                    Response::HTTP_CONFLICT
                ));
                return;
            }

            if ($candidate instanceof ForeignKeyConstraintViolationException) {
                $event->setResponse(new JsonResponse(
                    [
                        'error' => 'foreign_key',
                        'message' => 'Impossible de réaliser l\'opération : un autre enregistrement dépend de celui-ci (suppression bloquée ou référence invalide).',
                    ],
                    Response::HTTP_CONFLICT
                ));
                return;
            }

            if ($candidate instanceof NotNullConstraintViolationException) {
                $event->setResponse(new JsonResponse(
                    [
                        'error' => 'missing_field',
                        'message' => 'Un champ obligatoire est manquant. Vérifie le formulaire.',
                    ],
                    Response::HTTP_BAD_REQUEST
                ));
                return;
            }
        }
    }
}
