<?php

namespace App\Tags;

use Statamic\Tags\Svg;

class Icon extends Svg
{
    protected static $handle = 'icon';

    public function index()
    {
        $src = $this->params->get('src');
        $style = $this->params->get('suffix', 'regular');

        if ($style && $style !== 'regular') {
            $src .= '-' . $style;
        }

        $this->params['src'] = 'icons/' . $style . '/' . $src;

        return parent::index();
    }

    public function wildcard($src)
    {
        $this->params['src'] = $src;

        return $this->index();
    }
}
