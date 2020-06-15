<?php
header("Content-Type: text/html; charset=UTF-8");
require_once "Searcher.php";
$searcher = new Searcher();
// тест на локальном файле
$searcher->setFile("test");
$searcher->findSubString("мастер");

// тест на удаленном файле
$searcher->setFile("https://github.com/homoastricus/tz_searcher/test");
$searcher->findSubString("мастер");

