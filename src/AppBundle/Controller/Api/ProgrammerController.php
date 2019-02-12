<?php

namespace AppBundle\Controller\Api;

use AppBundle\Controller\BaseController;
use AppBundle\Entity\Programmer;
use AppBundle\Form\ProgrammerType;
use AppBundle\Form\UpdateProgrammerType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Api\ApiProblem;
use AppBundle\Api\ApiProblemException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProgrammerController extends BaseController
{
    /**
     * @Route("/api/programmers")
     * @Method("POST")
     */
    public function newAction(Request $request)
    {
        $programmer = new Programmer();
        $form = $this->createForm(new ProgrammerType(), $programmer);
        $this->processForm($request, $form);

        if (!$form->isValid()) {
            return $this->createValidationErrorResponse($form);
        }

        $programmer->setUser($this->findUserByUsername('weaverryan'));

        $em = $this->getDoctrine()->getManager();
        $em->persist($programmer);
        $em->flush();

        $response = $this->createApiResponse($programmer, 201);
        $programmerUrl = $this->generateUrl(
            'api_programmers_show',
            ['nickname' => $programmer->getNickname()]
        );
        $response->headers->set('Location', $programmerUrl);

        return $response;
    }

    /**
     * @Route("/api/programmers/{nickname}", name="api_programmers_show")
     * @Method("GET")
     */
    public function showAction($nickname)
    {
        $programmer = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findOneByNickname($nickname);

        if (!$programmer) {
            throw $this->createNotFoundException(sprintf(
                'No programmer found with nickname "%s"',
                $nickname
            ));
        }

        $response = $this->createApiResponse($programmer, 200);

        return $response;
    }

    /**
     * @Route("/api/programmers")
     * @Method("GET")
     */
    public function listAction()
    {
        $programmers = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findAll();

        $response = $this->createApiResponse(['programmers' => $programmers], 200);

        return $response;
    }

    /**
     * @Route("/api/programmers/{nickname}")
     * @Method({"PUT", "PATCH"})
     */
    public function updateAction($nickname, Request $request)
    {
        $programmer = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findOneByNickname($nickname);

        if (!$programmer) {
            throw $this->createNotFoundException(sprintf(
                'No programmer found with nickname "%s"',
                $nickname
            ));
        }

        $form = $this->createForm(new UpdateProgrammerType(), $programmer);
        $this->processForm($request, $form);

        if (!$form->isValid()) {
            return $this->createValidationErrorResponse($form);
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($programmer);
        $em->flush();

        $response = $this->createApiResponse($programmer, 200);

        return $response;
    }

    /**
     * @Route("/api/programmers/{nickname}")
     * @Method("DELETE")
     */
    public function deleteAction($nickname)
    {
        $programmer = $this->getDoctrine()
            ->getRepository('AppBundle:Programmer')
            ->findOneByNickname($nickname);

        if ($programmer) {
            // debated point: should we 404 on an unknown nickname?
            // or should we just return a nice 204 in all cases?
            // we're doing the latter
            $em = $this->getDoctrine()->getManager();
            $em->remove($programmer);
            $em->flush();
        }

        return new Response(null, 204);
    }

    private function processForm(Request $request, FormInterface $form)
    {
        $data = json_decode($request->getContent(), true);

       /*
        * In ProgrammerController , search for json_decode - 
        * you'll find it in processForm();
        * if null === $data then we need to return that 400 status code;
        * So if we return a Response from processForm()
        * newAction will continue on like normal. The only way we can
        * break the flow from inside processForm() is by throwing an exception.
        * But as you're probably thinking, if you throw an exception in Symfony, 
        * that turns into a 500 error. We need a 400 error.
        * Solution: Throw an HttpException from HttpKernel. It has 2 
        * arguments: the status code - 400 - and a message - "Invalid JSON"
        */
        if (null === $data) {
            $apiProblem = new ApiProblem(
                400, ApiProblem::TYPE_INVALID_REQUEST_BODY_FORMAT
            );
            throw new ApiProblemException($apiProblem);
        }

        $clearMissing = $request->getMethod() != 'PATCH';
        $form->submit($data, $clearMissing);
    }

    private function getErrorsFromForm(FormInterface $form)
    {
        $errors = array();
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }

        return $errors;
    }

    private function createValidationErrorResponse(FormInterface $form)
    {
        $errors = $this->getErrorsFromForm($form);

        $apiProblem = new ApiProblem(
            400, 
            ApiProblem::TYPE_VALIDATION_ERROR
        );

        $apiProblem->set('errors', $errors);

        $response = new JsonResponse($apiProblem->toArray(), $apiProblem->getStatusCode());
        $response->headers->set("Content-Type", "application/problem+json");
        return $response;
    }
}
