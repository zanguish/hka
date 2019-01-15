A  http client written in PHP for keep-alive
============================================

Requirements
-------------
PHP >= 7.0.0

Install
-------
    composer require zanguish/php-keep-alive

Usage
-------
    echo Hka::get('http://baidu.com') . PHP_EOL;
    //print_r(Hka::getLastStats());
    //print_r(Hka::getLastRepHeaders());
    //print_r(Hka::getLastVars());
    echo PHP_EOL . '------------------------------------------' . PHP_EOL;
    
    echo Hka::post('http://baidu.com', ['a' => 'b']) . PHP_EOL;
    //print_r(Hka::getLastStats());
    //print_r(Hka::getLastRepHeaders());
    //print_r(Hka::getLastVars());