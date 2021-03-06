<?php

namespace AppBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use AppBundle\Api\ApiProblemException;
use AppBundle\Api\ApiProblem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
	private $debug;

	public function __construct($debug)
	{
		$this->debug = $debug;
	}

	public function onKernelException(GetResponseForExceptionEvent $event)
	{
		// only reply to /api URLs
		if (strpos($event->getRequest()->getPathInfo(), '/api') !== 0) {
			return;
		}
		
		$e = $event->getException();

		$statusCode = $e instanceof HttpExceptionInterface ?
						$e->getStatusCode() : 500;

		if ($this->debug && $statusCode >= 500) {
			return;
		}

		if ($e instanceof ApiProblemException) {
			$apiProblem = $e->getApiProblem();
		} else {
			$apiProblem = new ApiProblem($statusCode);

			// in 'dev' or 'test' environment (debug = true),
			// in this case, don't use EventSubscriber to handle
			// but using standard, beautiful 'stacktrace'
			if ($e instanceof HttpExceptionInterface) {
				$apiProblem->set('detail', $e->getMessage());
			}
		}

		$data = $apiProblem->toArray();
		if ($data['type'] != 'about:blank') {
			$data['type'] = 'http://localhost:8000/docs/errors#'.$data['type'];
		}

		$response = new JsonResponse(
			$data,
			$apiProblem->getStatusCode()
		);

		$response->headers->set('Content-Type', 'application/problem+json');

		$event->setResponse($response);
	}

	public static function getSubscribedEvents()
	{
		return array(
			KernelEvents::EXCEPTION => 'onKernelException'
		);
	}
}
