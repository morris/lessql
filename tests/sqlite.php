<?php

require 'TestBase.php';
require 'vendor/autoload.php';

TestBase::$pdo = new \PDO('sqlite:tests/test.sqlite');
