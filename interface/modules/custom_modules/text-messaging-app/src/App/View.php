<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All rights reserved
 */

namespace Juggernaut\App;

use Juggernaut\App\Exceptions\ViewNotFoundException;

class View
{

    protected string $view;
    protected array $params;

    public function __construct(
        $view,
        $params = []
    )
    {
        $this->view = $view;
        $this->params = $params;
    }

    /**
     * @return string
     * @throws ViewNotFoundException
     */
    public function render(): string
    {
        $viewFile = VIEW_PATH . '/' . $this->view . '.php';
        if (! file_exists($viewFile)) {
            throw new ViewNotFoundException();
        }
        ob_start();
        include $viewFile;
        return (string) ob_get_clean();
    }
}
