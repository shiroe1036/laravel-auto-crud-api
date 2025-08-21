<?php

namespace FivoTech\LaravelAutoCrud\Facades;

use Illuminate\Support\Facades\Facade;

class AutoCrud extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'auto-crud';
    }
}
