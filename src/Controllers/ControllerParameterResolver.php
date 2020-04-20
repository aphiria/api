<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2020 David Young
 * @license   https://github.com/aphiria/aphiria/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Api\Controllers;

use Aphiria\Net\Formatting\UriParser;
use Aphiria\Net\Http\ContentNegotiation\ContentNegotiator;
use Aphiria\Net\Http\ContentNegotiation\IContentNegotiator;
use Aphiria\Net\Http\ContentNegotiation\MediaTypeFormatters\SerializationException;
use Aphiria\Net\Http\IRequest;
use ReflectionParameter;

/**
 * Defines the default controller parameter resolver
 */
final class ControllerParameterResolver implements IControllerParameterResolver
{
    /** @var IContentNegotiator The content negotiator */
    private IContentNegotiator $contentNegotiator;
    /** @var UriParser The URI parser to use */
    private UriParser $uriParser;

    /**
     * @param IContentNegotiator|null $contentNegotiator The content negotiator, or null if using the default negotiator
     * @param UriParser|null $uriParser The URI parser to use, or null if using the default parser
     */
    public function __construct(IContentNegotiator $contentNegotiator = null, UriParser $uriParser = null)
    {
        $this->contentNegotiator = $contentNegotiator ?? new ContentNegotiator();
        $this->uriParser = $uriParser ?? new UriParser();
    }

    /**
     * @inheritdoc
     */
    public function resolveParameter(
        ReflectionParameter $reflectionParameter,
        IRequest $request,
        array $routeVariables
    ) {
        $queryStringVars = $this->uriParser->parseQueryString($request->getUri());

        if ($reflectionParameter->getClass() !== null) {
            return $this->resolveObjectParameter(
                $reflectionParameter,
                $request
            );
        }

        if (isset($routeVariables[$reflectionParameter->getName()])) {
            return $this->resolveScalarParameter($reflectionParameter, $routeVariables[$reflectionParameter->getName()]);
        }

        if (isset($queryStringVars[$reflectionParameter->getName()])) {
            return $this->resolveScalarParameter($reflectionParameter, $queryStringVars[$reflectionParameter->getName()]);
        }

        if ($reflectionParameter->isDefaultValueAvailable()) {
            return $reflectionParameter->getDefaultValue();
        }

        if ($reflectionParameter->allowsNull()) {
            return null;
        }

        throw new MissingControllerParameterValueException(
            "No valid value for parameter {$reflectionParameter->getName()}"
        );
    }

    /**
     * Resolves an object parameter using content negotiator
     *
     * @param ReflectionParameter $reflectionParameter The parameter to resolve
     * @param IRequest $request The current request
     * @return object|null The resolved parameter
     * @throws FailedRequestContentNegotiationException Thrown if the request content negotiation failed
     * @throws MissingControllerParameterValueException Thrown if there was no valid value for the parameter
     * @throws RequestBodyDeserializationException Thrown if the request body could not be deserialized
     */
    private function resolveObjectParameter(
        ReflectionParameter $reflectionParameter,
        IRequest $request
    ): ?object {
        if ($request->getBody() === null) {
            if (!$reflectionParameter->allowsNull()) {
                throw new MissingControllerParameterValueException(
                    "Body is null when resolving parameter {$reflectionParameter->getName()}"
                );
            }

            return null;
        }

        $type = $reflectionParameter->getType()->getName();
        $requestContentNegotiationResult = $this->contentNegotiator->negotiateRequestContent(
            $type,
            $request
        );
        $mediaTypeFormatter = $requestContentNegotiationResult->getFormatter();

        if ($mediaTypeFormatter === null) {
            if (!$reflectionParameter->allowsNull()) {
                throw new FailedRequestContentNegotiationException(
                    "Failed to negotiate request content with type $type"
                );
            }

            return null;
        }

        try {
            return $mediaTypeFormatter
                ->readFromStream($request->getBody()->readAsStream(), $type);
        } catch (SerializationException $ex) {
            if (!$reflectionParameter->allowsNull()) {
                throw new RequestBodyDeserializationException(
                    "Failed to deserialize request body when resolving parameter {$reflectionParameter->getName()}",
                    0,
                    $ex
                );
            }

            return null;
        }
    }

    /**
     * Resolves a scalar parameter to the correct scalar value
     *
     * @param ReflectionParameter $reflectionParameter The parameter to resolve
     * @param mixed $rawValue The raw value to convert
     * @return mixed The raw value converted to the appropriate scalar type
     * @throws FailedScalarParameterConversionException Thrown if the scalar parameter could not be converted
     */
    private function resolveScalarParameter(ReflectionParameter $reflectionParameter, $rawValue)
    {
        $type = $reflectionParameter->getType() === null ? null : $reflectionParameter->getType()->getName();

        switch ($type) {
            case 'int':
                return (int)$rawValue;
            case 'float':
                return (float)$rawValue;
            case 'string':
                return (string)$rawValue;
            case 'bool':
                return (bool)$rawValue;
            case null:
                // Do not attempt to convert it
                return $rawValue;
            case 'array':
                throw new FailedScalarParameterConversionException('Cannot automatically resolve array types - you must either read the body or the query string inside the controller method');
            default:
                throw new FailedScalarParameterConversionException("Failed to convert value to {$reflectionParameter->getType()->getName()}");
        }
    }
}
