<?php

require 'TestBase.php';
require 'vendor/autoload.php';

TestBase::$pdo = new \PDO('pgsql:host=127.0.0.1;port=5432;dbname=test;user=postgres');
