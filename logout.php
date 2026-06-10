<?php
session_start();
session_destroy();
header('Location: /Administracion/index.php');
exit;
