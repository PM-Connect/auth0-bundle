<?php
declare(strict_types=1);

namespace Happyr\Auth0Bundle\Factory;

use Auth0\SDK\API\Authentication;
use Auth0\SDK\API\Management;
use Auth0\SDK\Exception\CoreException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class ManagementFactory
{
    protected $cacheItemPool;
    protected $authentication;
    protected $domain;
    protected $logger;

    /**
     * ManagementFactory constructor.
     */
    public function __construct(CacheItemPoolInterface $cacheItemPool, Authentication $authentication, $domain, LoggerInterface $logger = null)
    {
        $this->cacheItemPool = $cacheItemPool;
        $this->authentication = $authentication;
        $this->domain = $domain;
        $this->logger = $logger;
    }

    public function create()
    {
        $item = $this->cacheItemPool->getItem('auth0_management_access_token');

        if (!$item->isHit()) {
            $token = $this->authentication->oauth_token([
                'grant_type' => 'client_credentials',
                'audience' => sprintf('https://%s/api/v2/', $this->domain),
            ]);

            if (isset($token['error'])) {
                throw new CoreException($token['error_description']);
            }

            if ($this->logger) {
                $this->logger->debug("Got new access token for Auth0 managment API. Scope: ".$token['scope']);
            }

            $item->set($token['access_token']);
            $item->expiresAfter((int)$token['expires_in']);
            $this->cacheItemPool->save($item);
        }

        return new Management($item->get(), $this->domain);
    }
}
