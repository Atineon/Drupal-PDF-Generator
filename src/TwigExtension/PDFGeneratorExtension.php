<?php

namespace Drupal\pdf_generator\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Drupal\common_api\Service\YamlConfigAPI;

class PDFGeneratorExtension extends AbstractExtension
{
  protected YamlConfigAPI $yml;

  public function __construct(
    YamlConfigAPI $yml
  ) {
    $this->yml = $yml;
  }

  public function getFunctions()
  {
    return [
      new TwigFunction('getPdfSettings', $this->getPdfSettings(...)),
    ];
  }

  public function getPdfSettings(string $format)
  {
    $styleConfig = $this->yml->getConfig("pdf_generator", "formats.yml");
    return array_merge($styleConfig[$format], ["pdf" => true]);
  }
}
