<?php
echo "Request method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "POST data: \n";
print_r($_POST);
echo "\nSession ID: " . session_id() . "\n";
