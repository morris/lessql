<?php

require 'TestBase.php';
require 'vendor/autoload.php';

TestBase::$pdo = new \PDO('mysql:host=127.0.0.1;dbname=test', 'root');
