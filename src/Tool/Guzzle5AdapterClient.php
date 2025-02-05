<?php
/**
* 2014-2021 Uebix di Di Bella Antonino
*
* NOTICE OF LICENSE
*
* This source file is subject to the Uebix commercial License
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to info@uebix.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this PrestaShop Module to newer
* versions in the future. If you wish to customize this PrestaShop Module for your
* needs please refer to info@uebix.com for more information.
*
*  @author    Uebix <info@uebix.com>
*  @copyright 2021-2021 Uebix
*  @license   commercial use only, contact info@uebix.com for licence
*  International Registered Trademark & Property of Uebix di Di Bella Antonino
*/

namespace League\OAuth2\Client\Tool;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\RequestInterface as GuzzleRequest;
use GuzzleHttp\Message\ResponseInterface as GuzzleResponse;
use Http\Client\HttpClient;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\ResponseFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Http\Client\Exception as HttplugException;
use GuzzleHttp\Exception as GuzzleExceptions;

/**
 * @author GeLo <geloen.eric@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Guzzle5AdapterClient implements HttpClient
{
    /**
     * @var ClientInterface
     */
    private $client;
    
    /**
     * @var ResponseFactory
     */
    private $responseFactory;
    
    /**
     * @param ClientInterface|null $client
     * @param ResponseFactory|null $responseFactory
     */
    public function __construct(ClientInterface $client = null, ResponseFactory $responseFactory = null)
    {
        $this->client = $client ?: new GuzzleClient();
        $this->responseFactory = $responseFactory ?: MessageFactoryDiscovery::find();
    }
    
    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $guzzleRequest = $this->createRequest($request);
        
        try {
            $response = $this->client->send($guzzleRequest);
        } catch (GuzzleExceptions\TransferException $e) {
            throw $this->handleException($e, $request);
        }
        
        return $this->createResponse($response);
    }
    
    /**
     * Converts a PSR request into a Guzzle request.
     *
     * @param RequestInterface $request
     *
     * @return GuzzleRequest
     */
    private function createRequest(RequestInterface $request)
    {
        $options = [
            'exceptions' => false,
            'allow_redirects' => false,
        ];
        
        $options['version'] = $request->getProtocolVersion();
        $options['headers'] = $request->getHeaders();
        $body = (string) $request->getBody();
        $options['body'] = '' === $body ? null : $body;
        
        return $this->client->createRequest(
            $request->getMethod(),
            (string) $request->getUri(),
            $options
            );
    }
    
    /**
     * Converts a Guzzle response into a PSR response.
     *
     * @param GuzzleResponse $response
     *
     * @return ResponseInterface
     */
    private function createResponse(GuzzleResponse $response)
    {
        $body = $response->getBody();
        
        return $this->responseFactory->createResponse(
            $response->getStatusCode(),
            null,
            $response->getHeaders(),
            isset($body) ? $body->detach() : null,
            $response->getProtocolVersion()
            );
    }
    
    /**
     * Converts a Guzzle exception into an Httplug exception.
     *
     * @param GuzzleExceptions\TransferException $exception
     * @param RequestInterface                   $request
     *
     * @return HttplugException
     */
    private function handleException(GuzzleExceptions\TransferException $exception, RequestInterface $request)
    {
        if ($exception instanceof GuzzleExceptions\ConnectException) {
            return new HttplugException\NetworkException($exception->getMessage(), $request, $exception);
        }
        
        if ($exception instanceof GuzzleExceptions\RequestException) {
            // Make sure we have a response for the HttpException
            if ($exception->hasResponse()) {
                $psr7Response = $this->createResponse($exception->getResponse());
                
                return new HttplugException\HttpException(
                    $exception->getMessage(),
                    $request,
                    $psr7Response,
                    $exception
                    );
            }
            
            return new HttplugException\RequestException($exception->getMessage(), $request, $exception);
        }
        
        return new HttplugException\TransferException($exception->getMessage(), 0, $exception);
    }
}
