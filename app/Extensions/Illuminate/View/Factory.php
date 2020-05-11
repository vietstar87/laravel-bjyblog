<?php

declare(strict_types=1);

namespace App\Extensions\Illuminate\View;

use Illuminate\View\Factory as BaseFactory;
use Str;

class Factory extends BaseFactory
{
    public function make($view, $data = [], $mergeData = [])
    {
        $view = Str::contains($view, 'breadcrumbs') || Str::startsWith($view, 'blueberry.') ? $view : 'blueberry.' . $view;

        return parent::make($view, $data, $mergeData);
    }
}
