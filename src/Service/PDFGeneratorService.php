<?php

namespace Drupal\pdf_generator\Service;

use Drupal\node\NodeInterface;

use Dompdf\Dompdf;
use Dompdf\Options;

use Symfony\Component\HttpFoundation\Response;

class PdfGeneratorService
{
    public function generatePDFFromRenderableNode(NodeInterface $node, string $module): Response
    {
        if ($node->bundle() !== 'article') {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        }

        // Render the Twig template to HTML.
        $build = [
            '#theme' => $bundle . '__' . $module . '__pdf__' . $format,
            '#node' => $node,
        ];
        $html = \Drupal::service('renderer')->renderRoot($build);
        $module_path = \Drupal::service('extension.list.module')->getPath('pdf_generator');

        $bootstrapIconsCss = file_get_contents($module_path . '/libraries/Bootstrap-icons-1.11.3/font/bootstrap-icons.min.css');
        $bootstrapCss = file_get_contents($module_path . '/libraries/Bootstrap-5.3.3/css/bootstrap.min.css');
        $bootstrapJs = file_get_contents($module_path . '/libraries/Bootstrap-5.3.3/js/bootstrap.bundle.min.js');
        $customCss = file_get_contents($module_path . '/css/custom.css');
        $finalHtml = '
            <html>
                <head>
                    <style>' . $bootstrapIconsCss . '</style>
                    <style>' . $bootstrapCss . '</style>
                    <style>' . $bootstrapJs . '</style>
                    <style>' . $customCss . '</style>
                </head>
                <body>
                    ' . $html . '
                    <script type="text/php">
                        if ( isset($pdf) ) {
                            $x = 800;
                            $y = 570;
                            $text = "{PAGE_NUM} / {PAGE_COUNT}";
                            $font = $fontMetrics->get_font("helvetica", "bold");
                            $size = 10;
                            $color = array(0,0,0);
                            $word_space = 0.0;
                            $char_space = 0.0;
                            $angle = 0.0; 
                            $pdf->page_text($x, $y, $text, $font, $size, $color, $word_space, $char_space, $angle);
                        }
                    </script>
                </body>
            </html>
        ';

        // Initialize Dompdf with options.
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($finalHtml);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // Output the generated PDF to Browser.
        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $html . '.pdf');

        return $response;
    }
}