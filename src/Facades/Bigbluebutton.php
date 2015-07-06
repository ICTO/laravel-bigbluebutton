<?php namespace Icto\Bigbluebutton\Facades;

use Illuminate\Support\Facades\Facade;

class Bigbluebutton extends Facade
{

    protected static function getFacadeAccessor()
    {
        return 'bigbluebutton';
    }

}