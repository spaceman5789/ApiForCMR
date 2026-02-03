<?php

namespace App\Controller;

use App\Service\PdfMergerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class PdfController extends AbstractController
{
    private $pdfMerger;

    public function __construct(PdfMergerService $pdfMerger)
    {
        $this->pdfMerger = $pdfMerger;
    }

    /**
     * @Route("/combine-pdfs", name="combine_pdfs", methods={"POST"})
     */
    public function combinePdfs(): JsonResponse
    {
        // URLs des fichiers PDF
        $pdfUrls = [
            "",
            "f",
        ];

        try {
            // Créer le fichier combiné
            $filename = $this->pdfMerger->mergePdfs($pdfUrls);

            // Générer un lien de téléchargement
            $downloadLink = $this->generateUrl(
                'app_download_file',
                ['filename' => $filename],
                true
            );

            return new JsonResponse([
                'message' => 'PDFs merged successfully!',
                'download_link' => $downloadLink
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/download/{filename}", name="app_download_file", methods={"GET"})
     */
    public function downloadFile(string $filename): Response
    {
        // Chemin complet vers le fichier
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $filename;

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier non trouvé.');
        }

        return $this->file($filePath, $filename, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
