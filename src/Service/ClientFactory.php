<?php

namespace SilverStripe\DiscovererBifrost\Service;

use Elastic\EnterpriseSearch\Client;
use Exception;
use SilverStripe\Core\Injector\Factory;

class ClientFactory implements Factory
{

    /**
     * @throws Exception
     */
    public function create(mixed $service, array $params = []) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        $host = $params['host'] ?? null;
        $token = $params['token'] ?? null;
        $httpClient = $params['http_client'] ?? null;

        $missingEnvVars = [];

        if (!$host) {
            $missingEnvVars[] = 'BIFROST_ENDPOINT';
        }

        if (!$token) {
            $missingEnvVars[] = 'BIFROST_PUBLIC_API_KEY';
        }

        if ($missingEnvVars) {
            throw new Exception(sprintf('Required ENV vars missing: %s', implode(', ', $missingEnvVars)));
        }

        if (!$httpClient) {
            throw new Exception('http_client required');
        }

        $config = [
            'host' => $host,
            'app-search' => [
                'token' => $token,
            ],
            'enterprise-search' => [
                'token' => $token,
            ],
            'client' => $httpClient,
        ];

        return new Client($config);
    }

}
