<?php

declare(strict_types=1);

namespace Mezzio\Authentication\Basic;

use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Authentication\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_shift;
use function base64_decode;
use function count;
use function explode;
use function preg_match;
use function sprintf;

class BasicAccess implements AuthenticationInterface
{
    /** @var UserRepositoryInterface */
    protected $repository;

    /** @var string */
    protected $realm;

    // phpcs:disable SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.InvalidFormat
    /** @var callable():ResponseInterface */
    protected $responseFactory;

    public function __construct(
        UserRepositoryInterface $repository,
        string $realm,
        callable $responseFactory
    ) {
        $this->repository = $repository;
        $this->realm      = $realm;

        // Ensures type safety of the composed factory
        $this->responseFactory = function () use ($responseFactory): ResponseInterface {
            /** @var ResponseInterface */
            return $responseFactory();
        };
    }

    public function authenticate(ServerRequestInterface $request): ?UserInterface
    {
        $authHeaders = $request->getHeader('Authorization');

        if (1 !== count($authHeaders)) {
            return null;
        }

        $authHeader = array_shift($authHeaders);

        if (! preg_match('/Basic (?P<credentials>.+)/', $authHeader, $match)) {
            return null;
        }

        $decodedCredentials = base64_decode($match['credentials'], true);

        if (false === $decodedCredentials) {
            return null;
        }

        $credentialParts = explode(':', $decodedCredentials, 2);

        if (2 !== count($credentialParts)) {
            return null;
        }

        [$username, $password] = $credentialParts;

        return $this->repository->authenticate($username, $password);
    }

    public function unauthorizedResponse(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->responseFactory)()
            ->withHeader(
                'WWW-Authenticate',
                sprintf('Basic realm="%s"', $this->realm)
            )
            ->withStatus(401);
    }
}
