<?php
namespace App\Controller;

use App\Service\leadService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class leadController extends AbstractController
{
    private $leadService;

    /**
     * @param leadService $leadService
     */
    public function __construct(leadService $leadService)
    {
        $this->leadService = $leadService;
    }
    /**
     * @Route(
     *     name="post_lead",
     *     path="/api/lead",
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return Response
     */
    public function createLead(Request $request): Response
    {
        $data = $this->leadService->createLead($request);
        return $this->json($data);
    }
    /**
     * @Route(
     *     name="post_leads",
     *     path="/api/leads",
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return Response
     */
    public function createLeads(Request $request): Response
    {
        $data = $this->leadService->createLeads($request);
        return $this->json($data);
    }
}