<?php

declare(strict_types=1);

namespace Akeneo\Connectivity\Connection\Infrastructure\EventSubscriber;

use Akeneo\Connectivity\Connection\Domain\Settings\Model\ValueObject\FlowType;
use Akeneo\Connectivity\Connection\Domain\Settings\Model\Write\Connection;
use Akeneo\Connectivity\Connection\Domain\Settings\Persistence\Repository\ConnectionRepository;
use Akeneo\Tool\Bundle\ApiBundle\Entity\Client;
use Akeneo\UserManagement\Bundle\Doctrine\ORM\Repository\UserRepository;
use Akeneo\UserManagement\Component\Model\User;
use FOS\OAuthServerBundle\Event\OAuthEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class OAuthEventSubscriber implements EventSubscriberInterface
{
    private ConnectionRepository $connectionRepository;
    private UserRepository $userRepository;

    public function __construct(
        ConnectionRepository $connectionRepository,
        UserRepository $userRepository
    ) {
        $this->connectionRepository = $connectionRepository;
        $this->userRepository = $userRepository;
    }

    public static function getSubscribedEvents()
    {
        return [OAuthEvent::POST_AUTHORIZATION_PROCESS => 'saveConnection'];
    }

    public function saveConnection(OAuthEvent $event)
    {
        $client = $event->getClient();
        $appUsername = 'yell-extenssion-username';
        $user = $this->userRepository->findOneBy(['username' => $appUsername]);
        //$user = $event->getUser();
        // @todo: CREATE USER WITH PERMISSIONS FROM THE SCOPE
        if (!$client instanceof Client || !$user instanceof User) {
            return;
        }
        $connection = $this->connectionRepository->findOneByCode('yellextension');

        if(null !== $connection) {
            return;
        }

        $connection = new Connection(
            'yellextension',
            'yell-extension',
            FlowType::DATA_DESTINATION,
            $client->getId(),
            $user->getId(), // user
            null,
            true
        );
        $this->connectionRepository->create($connection);
    }
}