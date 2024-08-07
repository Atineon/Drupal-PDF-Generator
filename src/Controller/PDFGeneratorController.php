<?php

namespace Drupal\pdf_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

use Dompdf\Dompdf;
use Dompdf\Options;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class PDFGeneratorController extends ControllerBase
{
    protected $entityTypeManager;

    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('entity_type.manager')
        );
    }
    public function generatePDFFromRenderableEntity(string $bundle, string $id, string $format = "a4")
    {
        $entity = $this->entityTypeManager->getStorage($bundle)->load($id);

        if (!$entity || !$entity instanceof EntityInterface) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        if ($entity instanceof NodeInterface) {
            // Render the Twig template to HTML.
            $build = [
                '#theme' => $entity->bundle() . '__pdf__' . $format,
                '#node' => $entity,
            ];
            $html = \Drupal::service('renderer')->renderRoot($build);
            $module_path = \Drupal::service('extension.list.module')->getPath('pdf_generator');

            $customCss = file_get_contents($module_path . '/css/custom.css');
            $finalHtml = '
            <html>
                <head>
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
            $dompdf->loadHtml($finalHtml, 'UTF-8');
            switch ($format) {
                case 'a4':
                    $dompdf->setPaper('A4');
                    break;
                case 'landscape':
                    $dompdf->setPaper('A4', 'landscape');
                    break;
                default:
                    $dompdf->setPaper('A4');
                    break;
            }
            $dompdf->render();

            $pdf_title = $entity->bundle() . "_" . str_replace(',', '', str_replace(' ', '_', $entity->get('title')->value));

            // Output the generated PDF to Browser.
            $response = new Response($dompdf->output());
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename=' . $pdf_title . '.pdf');

            return $response;
        } else {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
    }

    public function getTitle(): string
    {
        return $this->t('PDF Generator');
    }
}