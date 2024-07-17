<?php

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;

class CustomController extends ControllerBase
{
    public function customPage()
    {
        return [
            '#theme' => 'custom_template',
            '#data' => [
                'message' => 'Hello, this is a custom page!',
            ],
        ];
    }
}