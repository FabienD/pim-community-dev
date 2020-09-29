<?php
declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Infrastructure\InternalApi\Controller;

use Akeneo\Connectivity\Connection\Application\Webhook\Command\GenerateWebhookSecretCommand;
use Akeneo\Connectivity\Connection\Application\Webhook\Command\GenerateWebhookSecretHandler;
use Akeneo\Connectivity\Connection\Application\Webhook\Command\UpdateWebhookCommand;
use Akeneo\Connectivity\Connection\Application\Webhook\Command\UpdateWebhookHandler;
use Akeneo\Connectivity\Connection\Application\Webhook\Query\GetAConnectionWebhookHandler;
use Akeneo\Connectivity\Connection\Application\Webhook\Query\GetAConnectionWebhookQuery;
use Akeneo\Connectivity\Connection\Domain\Settings\Exception\ConstraintViolationListException;
use Akeneo\Connectivity\Connection\Domain\Webhook\Exception\ConnectionWebhookNotFoundException;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class WebhookController
{
    /** @var GetAConnectionWebhookHandler */
    private $getAConnectionWebhookHandler;

    /** @var UpdateWebhookHandler */
    private $updateConnectionWebhookHandler;

    /** @var SecurityFacade */
    private $securityFacade;

    /** @var GenerateWebhookSecretHandler */
    private $generateWebhookSecretHandler;

    public function __construct(
        SecurityFacade $securityFacade,
        GetAConnectionWebhookHandler $getAConnectionWebhookHandler,
        UpdateWebhookHandler $updateConnectionWebhookHandler,
        GenerateWebhookSecretHandler $generateWebhookSecretHandler
    ) {
        $this->getAConnectionWebhookHandler = $getAConnectionWebhookHandler;
        $this->updateConnectionWebhookHandler = $updateConnectionWebhookHandler;
        $this->securityFacade = $securityFacade;
        $this->generateWebhookSecretHandler = $generateWebhookSecretHandler;
    }

    public function get(Request $request): JsonResponse
    {
        $connectionWebhook = $this->getAConnectionWebhookHandler->handle(
            new GetAConnectionWebhookQuery($request->get('code', ''))
        );

        if (null === $connectionWebhook) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($connectionWebhook->normalize());
    }

    public function update(Request $request): JsonResponse
    {
        if (true !== $this->securityFacade->isGranted('akeneo_connectivity_connection_manage_settings')) {
            throw new AccessDeniedException();
        }

        try {
            $webhook = $this->updateConnectionWebhookHandler->handle(
                new UpdateWebhookCommand(
                    $request->get('code', ''),
                    $request->get('enabled'),
                    $request->get('url')
                )
            );
        } catch (ConnectionWebhookNotFoundException $webhookNotFoundException) {
            return new JsonResponse($webhookNotFoundException->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (ConstraintViolationListException $violationListException) {
            $errorList = $this->buildViolationResponse($violationListException->getConstraintViolationList());

            return new JsonResponse(
                ['errors' => $errorList, 'message' => $violationListException->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return new JsonResponse($webhook->normalize());
    }

    public function regenerateSecret(Request $request): JsonResponse
    {
        try {
            $this->generateWebhookSecretHandler->handle(
                new GenerateWebhookSecretCommand($request->get('code', ''))
            );

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (ConnectionWebhookNotFoundException $notFoundException) {
            return new JsonResponse($notFoundException->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    private function buildViolationResponse(ConstraintViolationListInterface $constraintViolationList): array
    {
        $errors = [];
        foreach ($constraintViolationList as $constraintViolation) {
            $errors[] = [
                'field' => $constraintViolation->getPropertyPath(),
                'message' => $constraintViolation->getMessage(),
            ];
        }

        return $errors;
    }
}
