<?php

namespace Drupal\pdf_generator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\common_api\Service\YamlConfigAPI;

use Dompdf\Dompdf;
use Dompdf\Options;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * This class allow you to generate pdf from a module called PDF Generator
 */
class PDFGeneratorController extends ControllerBase
{
    protected EntityTypeManagerInterface $entityTypeManager;
    protected YamlConfigAPI $yml;

    /**
     * Constructor
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
     * @param \Drupal\common_api\Service\YamlConfigAPI $yml
     */
    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        YamlConfigAPI $yml
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->yml = $yml;
    }

    /**
     * Create container interface to avoid dependecies injections
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @return static
     */
    public static function create(ContainerInterface $container): static
    {
        return new static(
            $container->get('entity_type.manager'),
            $container->get('common_api.yaml')
        );
    }

    /**
     * Generate a PDF from a renderable entity
     * @param string $bundle
     * @param string $id
     * @param string $format
     * @param mixed $style
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generatePDFFromRenderableEntity(string $bundle, string $id, string $format, ?string $style): Response
    {
        // Load the current entity
        $entity = $this->entityTypeManager->getStorage($bundle)->load($id);

        // Return error if entity not found
        if (!$entity || !($entity instanceof EntityInterface)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        // Loading styles
        $styleConfig = $this->yml->getConfig("pdf_generator", "formats.yml");

        // Return error if format style not found
        if (!$styleConfig[$format]) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        if (!($entity instanceof NodeInterface) || !($entity instanceof TermInterface)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        // Build the Twig template to HTML.
        // TODO: throw a page not found if the theme is not found
        $build = [
            '#theme' => $entity->bundle() . '__pdf__' . $format . '__' . $style != NULL ? $style : "full",
            '#entity' => $entity,
        ];
        $html = \Drupal::service('renderer')->renderRoot($build);

        // Load styles
        $modulePath = \Drupal::service('extension.list.module')->getPath('pdf_generator');
        $cssModulePath = \Drupal::service('extension.list.module')->getPath('css_overwrites');

        $bootstrapIconsCss = file_get_contents($modulePath . '/libraries/Bootstrap-icons-1.11.3/font/bootstrap-icons.min.css');
        $bootstrapCss = file_get_contents($modulePath . '/libraries/Bootstrap-5.3.3/css/bootstrap.min.css');
        $cssOverwrite = file_get_contents($cssModulePath . '/css/text-styles.css');
        $customCss = file_get_contents($modulePath . '/css/custom.css');

        $finalHtml = '
                <html>
                    <head>
                        <style>' . $bootstrapIconsCss . '</style>
                        <style>' . $bootstrapCss . '</style>
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

        // Render the PDF
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($finalHtml, 'UTF-8');
        $dompdf->setPaper($styleConfig[$format]['page_size'], $styleConfig[$format]['orientation']);
        $dompdf->render();

        // Return a response that download the rendered pdf in the attachment
        $pdf_title = $entity->bundle() . "_" . str_replace([' ', ','], ['_', ''], $entity->label());

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $pdf_title . '.pdf');
        return $response;
    }

    /**
     * Return the title of the module
     * @return string
     */
    public function getTitle(): string
    {
        return $this->t('PDF Generator');
    }
}
