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
            $css_module_path = \Drupal::service('extension.list.module')->getPath('css_overwrites');
            $module_path = \Drupal::service('extension.list.module')->getPath('pdf_generator');

            $bootstrapIconsCss = file_get_contents($module_path . '/libraries/Bootstrap-icons-1.11.3/font/bootstrap-icons.min.css');
            $bootstrapCss = file_get_contents($module_path . '/libraries/Bootstrap-5.3.3/css/bootstrap.min.css');
            $bootstrapJs = file_get_contents($module_path . '/libraries/Bootstrap-5.3.3/js/bootstrap.bundle.min.js');
            $cssOverwrite = file_get_contents($css_module_path . '/css/text-styles.css');
            $customCss = file_get_contents($module_path . '/css/custom.css');
            $finalHtml = '
                <html>
                    <head>
                        <style>' . $bootstrapIconsCss . '</style>
                        <style>' . $bootstrapCss . '</style>
                        <style>' . $bootstrapJs . '</style>
                        <style>' . $cssOverwrite . '</style>
                        <style>' . $customCss . '</style>
                    </head>
                    <body>' . $html . '</body>
                </html>';

            // Initialize Dompdf with options.
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('isPhpEnabled', true);

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

            // return [
            //     '#theme' => $entity->bundle() . '__pdf__' . $format,
            //     '#node' => $entity,
            // ];

            // Return a response that download the rendered pdf in the attachment.
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