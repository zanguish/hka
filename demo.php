<?php

require 'vendor/autoload.php';

use Hka\Hka;

echo Hka::get('http://baidu.com') . PHP_EOL;
//print_r(Hka::getLastStats());
//print_r(Hka::getLastRepHeaders());
//print_r(Hka::getLastVars());
echo PHP_EOL . '------------------------------------------' . PHP_EOL;
echo Hka::post('http://baidu.com', ['a' => 'b']) . PHP_EOL;
//print_r(Hka::getLastStats());
//print_r(Hka::getLastRepHeaders());
//print_r(Hka::getLastVars());