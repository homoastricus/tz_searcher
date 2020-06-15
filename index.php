<?php
header("Content-Type: text/html; charset=UTF-8");
require_once "TZ_Searcher.php";
$tz_searcher = new TZ_Searcher();

// тест на локальном файле
$tz_searcher->setFile("test");
$tz_searcher->findSubString("font-family");

// тест на удаленном файле
$tz_searcher->setFile("http://4tarelki.ru/css/styles2.css");
$tz_searcher->findSubString("font-family");

