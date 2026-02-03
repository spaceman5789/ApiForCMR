<?php

namespace App\Service;

use setasign\Fpdi\Fpdi;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PdfMergerService
{
    private string $uploadsDir;

    public function __construct(ParameterBagInterface $params)
    {
        // Construire le chemin vers public/uploads
        $this->uploadsDir = $params->get('kernel.project_dir') . '/public/uploads/';
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }
    }

    public function mergePdfs(array $pdfUrls): string
    {
        $pdf = new Fpdi();

        foreach ($pdfUrls as $url) {
            // Télécharger le contenu du fichier PDF
            $content = file_get_contents($url);

            if ($content === false) {
                throw new \Exception("Impossible de télécharger le fichier PDF depuis l'URL : $url");
            }

            // Sauvegarder temporairement le fichier PDF
            $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
            file_put_contents($tempPdf, $content);

            // Charger les pages du fichier temporaire dans FPDI
            $pageCount = $pdf->setSourceFile($tempPdf);
            for ($i = 1; $i <= $pageCount; $i++) {
                $pdf->AddPage();
                $pdf->useTemplate($pdf->importPage($i));
            }

            // Supprimer le fichier temporaire
            unlink($tempPdf);
        }

        $timestamp = time();
        $filename = 'combined_' . $timestamp . '.pdf';
        $outputPath = $this->uploadsDir . $filename;

        // Sauvegarder le fichier combiné
        $pdf->Output($outputPath, 'F');

        // Vérification : Fichier créé ?
        if (!file_exists($outputPath)) {
            throw new \Exception("Le fichier combiné n'a pas été créé !");
        }
        return $filename;
        // Retourner le chemin du fichier généré
       // return $outputPath;
    }
}
